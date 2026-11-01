<?php

namespace App\Console\Commands;

use App\CentralLogics\Helpers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Madnest\Madzipper\Facades\Madzipper;

class InstallablePackage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'prepare:installable';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an installable package.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        /*Helpers::remove_dir('.idea');*/
        Artisan::call('debugbar:clear');

        // bootstrap/cache/ is git-ignored but a release zip is built from the working tree, so
        // whatever the build machine compiled goes out with the package. A shipped route cache is
        // the worst of them: Laravel reads it instead of registering routes, so the client's panel
        // routes are this machine's -- and since the wizard stub is swapped in below, a cache
        // compiled after that point holds only routes/install.php. Cleared here so the package
        // cannot carry one.
        foreach (['route:clear', 'config:clear', 'view:clear'] as $command) {
            Artisan::call($command);
        }

        Helpers::remove_dir('storage/app/public');
        Storage::disk('public')->makeDirectory('/');
        Madzipper::make('installation/backup/public.zip')->extractTo('storage/app');

        $dot_env = base_path('.env');
        $new_env = base_path('.env.example');
        copy($new_env, $dot_env);

        // Snapshot the real provider so the wizard has something to restore when it
        // finishes. Guarded, because once a package has been prepared the live .php IS the
        // wizard stub: snapshotting that writes the stub into .txt, the wizard then restores
        // the stub over itself, and the site never leaves wizard mode. Re-running this
        // command used to be what poisoned .txt.
        $liveRoutes = base_path('app/Providers/RouteServiceProvider.php');
        $restoreRoutes = base_path('app/Providers/RouteServiceProvider.txt');

        if (Helpers::is_wizard_route_provider($liveRoutes)) {
            if (Helpers::is_wizard_route_provider($restoreRoutes)) {
                $this->error('app/Providers/RouteServiceProvider.php and RouteServiceProvider.txt are both wizard stubs - no copy of the real provider is left to restore. Restore both from version control before preparing a package.');

                return 1;
            }

            $this->warn('Already prepared: RouteServiceProvider.php is a wizard stub, keeping the existing RouteServiceProvider.txt.');
        } elseif (!copy($liveRoutes, $restoreRoutes)) {
            $this->error('Could not refresh RouteServiceProvider.txt - package not prepared.');

            return 1;
        }

        $routes = base_path('app/Providers/RouteServiceProvider.php');
        $new_routes = base_path('installation/activate_install_routes.txt');
        copy($new_routes, $routes);

        return 0;
    }
}
