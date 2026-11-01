<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Services\System\DatabaseBackupService;
use Illuminate\Http\UploadedFile;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The backup and restore screens are the only place in the panel where one
 * click can destroy the platform, so the guards around them are asserted rather
 * than assumed: the file-name whitelist, the content sniff on an uploaded dump,
 * the typed token, the password re-check, and the SQL splitter a restore feeds
 * the server statement by statement.
 *
 * Nothing here writes to the database. The one test that touches the vault
 * writes a structure-only dump and deletes it again.
 */
class DatabaseBackupTest extends TestCase
{
    private function admin(): Admin
    {
        $admin = Admin::find(1);
        $this->assertNotNull($admin, 'admin id 1 must exist');

        return $admin;
    }

    private function asAdmin(): self
    {
        $admin = $this->admin();

        return $this->actingAs($admin, 'admin')
            ->withSession(['login_remember_token' => $admin->login_remember_token]);
    }

    public function test_both_pages_render(): void
    {
        $this->asAdmin()->get('admin/business-settings/database/backup')
            ->assertOk()
            ->assertSee('Backup Database', false)
            ->assertSee('id="dbk-create-form"', false);

        $this->asAdmin()->get('admin/business-settings/database/restore')
            ->assertOk()
            ->assertSee('Restore Database', false)
            ->assertSee('id="dbk-restore-form"', false);
    }

    public function test_the_sidebar_links_both_pages(): void
    {
        $this->asAdmin()->get('admin/business-settings/database/backup')
            ->assertOk()
            ->assertSee('data-id="maint-backup"', false)
            ->assertSee('data-id="maint-restore"', false);
    }

    /** A path is built from this name, so anything but the shape written here is refused. */
    public function test_the_file_name_whitelist_rejects_traversal(): void
    {
        $service = app(DatabaseBackupService::class);

        foreach (['backup-db-20260101-000000.sql', 'backup-db-20260101-000000.sql.gz'] as $name) {
            $this->assertTrue($service->isValidName($name), $name.' should be accepted');
        }

        foreach ([
            '../../../.env',
            'backup.sql/../../../etc/passwd',
            '/etc/passwd',
            'shell.php',
            'backup.sql.php',
            '.htaccess',
            'a..b.sql',
            '',
        ] as $name) {
            $this->assertFalse($service->isValidName($name), $name.' should be refused');
        }
    }

    /** An uploaded dump is executed with full rights, so the extension is not taken on trust. */
    public function test_an_uploaded_file_is_sniffed_not_trusted(): void
    {
        $service = app(DatabaseBackupService::class);
        $looksLikeDump = new ReflectionMethod($service, 'looksLikeDump');
        $looksLikeDump->setAccessible(true);

        $cases = [
            ["<?php system(\$_GET['c']); ?>\nINSERT INTO x VALUES (1);" => false],
            ['<html><script>alert(1)</script></html>' => false],
            ["#!/bin/bash\nrm -rf /\n" => false],
            ['' => false],
            ["-- dump\nCREATE TABLE `a` (`id` int);\n" => true],
        ];

        foreach ($cases as $case) {
            foreach ($case as $body => $expected) {
                $path = tempnam(sys_get_temp_dir(), 'dbk');
                file_put_contents($path, $body);

                $this->assertSame($expected, $looksLikeDump->invoke($service, $path, false), 'sniff on: '.substr($body, 0, 24));

                unlink($path);
            }
        }
    }

    public function test_a_restore_is_refused_without_the_token(): void
    {
        $this->asAdmin()
            ->post('admin/business-settings/database/restore/run', [
                'file' => 'backup-db-20260101-000000.sql.gz',
                'confirmation' => 'restore please',
                'password' => 'whatever',
            ])
            ->assertSessionHasErrors('confirmation');
    }

    public function test_a_restore_is_refused_with_the_wrong_password(): void
    {
        $this->asAdmin()
            ->post('admin/business-settings/database/restore/run', [
                'file' => 'backup-db-20260101-000000.sql.gz',
                'confirmation' => 'RESTORE',
                'password' => 'not-the-admin-password-'.uniqid(),
            ])
            ->assertSessionHasErrors('password');
    }

    /**
     * Exploding a dump on ";" cuts every INSERT holding one inside a string, and
     * a trigger body is nothing but semicolons. This is the routine a restore
     * runs over every byte of the file.
     */
    public function test_the_statement_scanner_splits_a_dump_correctly(): void
    {
        $service = app(DatabaseBackupService::class);
        $scanLine = new ReflectionMethod($service, 'scanLine');
        $scanLine->setAccessible(true);

        $dump = <<<'SQL'
        -- a comment; with a semicolon
        /*!40101 SET NAMES utf8mb4 */;
        /* plain block
           comment spanning ; lines */
        CREATE TABLE `orders` (
          `id` int NOT NULL, -- inline comment;
          `note` varchar(255) DEFAULT 'semi; colon'
        ) ENGINE=InnoDB;
        INSERT INTO `orders` VALUES (1, 'it\'s here; ok'),
            (2, "double \" quote; too"),
            (3, 'a''b; c');
        DELIMITER ;;
        CREATE TRIGGER `x` BEFORE INSERT ON `orders` FOR EACH ROW BEGIN
          SET NEW.id = 1;
          SET NEW.note = 'hi;';
        END;;
        DELIMITER ;
        INSERT INTO `last` VALUES (9);
        SQL;

        $context = ['buffer' => '', 'quote' => null, 'block' => false, 'delimiter' => ';'];
        $statements = [];
        $emit = function (string $sql) use (&$statements) {
            $sql = trim($sql);
            if ($sql !== '') {
                $statements[] = $sql;
            }
        };

        foreach (explode("\n", $dump) as $line) {
            $scanLine->invokeArgs($service, [$line, &$context, $emit]);
        }

        $this->assertCount(5, $statements, 'comments must be dropped and quoted semicolons must not split a statement');
        $this->assertStringStartsWith('/*!40101 SET NAMES', $statements[0], 'conditional comments carry real statements');
        $this->assertStringContainsString("'semi; colon'", $statements[1]);
        $this->assertStringContainsString("'a''b; c'", $statements[2]);
        $this->assertStringContainsString('SET NEW.note', $statements[3], 'a trigger body is one statement, semicolons and all');
        $this->assertSame('INSERT INTO `last` VALUES (9)', $statements[4], 'DELIMITER must be consumed, not sent to the server');
    }

    /** The create and delete buttons, end to end through the routes. */
    public function test_a_backup_can_be_created_and_deleted_through_the_routes(): void
    {
        $service = app(DatabaseBackupService::class);
        $before = array_column($service->backups(), 'name');

        $this->asAdmin()
            ->post('admin/business-settings/database/backup', [
                'compress' => '1',
                'scope' => 'structure',
                'note' => 'phpunit route test',
            ])
            ->assertRedirect();

        $after = array_column($service->backups(), 'name');
        $created = array_values(array_diff($after, $before));

        $this->assertCount(1, $created, 'the POST should have written exactly one dump');
        $this->assertStringEndsWith('.sql.gz', $created[0]);
        $this->assertFileExists($service->vaultPath($created[0]));

        $this->asAdmin()
            ->delete('admin/business-settings/database/backup/'.$created[0])
            ->assertRedirect();

        $this->assertFileDoesNotExist($service->vaultPath($created[0]));
        $this->assertSame($before, array_column($service->backups(), 'name'));
    }

    /** A .sql extension on a PHP payload must not get it into the vault. */
    public function test_an_uploaded_php_payload_never_reaches_the_vault(): void
    {
        $service = app(DatabaseBackupService::class);
        $before = array_column($service->backups(), 'name');

        $this->asAdmin()
            ->post('admin/business-settings/database/restore/upload', [
                'backup_file' => UploadedFile::fake()->createWithContent(
                    'evil.sql',
                    "<?php system(\$_GET['c']); ?>\nINSERT INTO users VALUES (1);"
                ),
            ])
            ->assertRedirect();

        $this->assertSame($before, array_column($service->backups(), 'name'),
            'a sniffed-out payload must leave the vault untouched');
    }

    /** A structure-only dump must carry every table and no rows at all. */
    public function test_a_structure_only_dump_carries_no_rows(): void
    {
        $service = app(DatabaseBackupService::class);

        $manifest = $service->create([
            'compress' => false,
            'structure_only' => true,
            'type' => 'manual',
            'note' => 'phpunit',
        ]);

        try {
            $sql = file_get_contents($service->vaultPath($manifest['file']));

            $this->assertSame(0, $manifest['rows']);
            $this->assertSame(0, substr_count($sql, 'INSERT INTO'));
            $this->assertSame(count($service->tableNames()), $manifest['tables']);
            $this->assertSame(hash_file('sha256', $service->vaultPath($manifest['file'])), $manifest['checksum']);
        } finally {
            $service->delete($manifest['file']);
        }

        $this->assertFileDoesNotExist($service->vaultPath($manifest['file']));
    }
}
