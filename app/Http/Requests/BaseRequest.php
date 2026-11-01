<?php

namespace App\Http\Requests;

use App\Rules\EmailAddress;
use App\Support\ApiEnvelope;
use App\Rules\ImageFile;
use App\Rules\PhoneNumber;
use App\Rules\StrongPassword;
use App\Rules\VideoFile;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator as ValidatorFactory;

class BaseRequest extends FormRequest
{
    protected const MAX_NUMERIC_VALUE = 9999999999999999.9999;
    public function authorize(): bool
    {
        return true;
    }
    public function rules(): array
    {
        return [
            //
        ];
    }
    public function perPage(): int
    {
        $perPage = (int) ($this->input('limit') ?: config('default_pagination'));

        return $perPage > 0 ? $perPage : (int) config('default_pagination');
    }
    public function page(): int
    {
        return max(1, (int) ($this->input('offset') ?: 1));
    }
    protected function numericRule(float $min = 0): array
    {
        return ["numeric", "min:{$min}", "max:" . self::MAX_NUMERIC_VALUE];
    }
    protected function passwordRule(string $presence = 'required'): array
    {
        return StrongPassword::rules($presence);
    }
    protected function basicPasswordRule(string $presence = 'required'): array
    {
        return StrongPassword::basicRules($presence);
    }
    protected function imageRule(string $presence = 'nullable', ?int $maxKilobytes = null): array
    {
        return ImageFile::rules($presence, $maxKilobytes);
    }
    protected function videoRule(string $presence = 'nullable', ?int $maxKilobytes = null): array
    {
        return VideoFile::rules($presence, $maxKilobytes);
    }
    protected function phoneRule(string $presence = 'required', mixed $unique = null, ?int $min = PhoneNumber::MIN_LENGTH, ?int $max = PhoneNumber::MAX_LENGTH): array
    {
        return PhoneNumber::rules($presence, $unique, $min, $max);
    }
    protected function emailRule(string $presence = 'required', mixed $unique = null, ?int $max = EmailAddress::MAX_LENGTH): array
    {
        return EmailAddress::rules($presence, $unique, $max);
    }
    protected function iniBytes(string $directive): int
    {
        $value = trim((string) ini_get($directive));

        if ($value === '' || (int) $value <= 0) {
            return 0;
        }

        $bytes = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $bytes * 1024 * 1024 * 1024,
            'm' => $bytes * 1024 * 1024,
            'k' => $bytes * 1024,
            default => $bytes,
        };
    }
    protected function exceedsPostMaxSize(): bool
    {
        $limit = $this->iniBytes('post_max_size');

        if ($limit === 0) {
            return false;
        }

        return (int) $this->server('CONTENT_LENGTH', 0) > $limit;
    }
    protected function discardedUploadMessage(): ?string
    {
        if (! $this->isMultipartRequest()) {
            return null;
        }

        $postLimit = (string) ini_get('post_max_size');

        if ($this->exceedsPostMaxSize()) {
            $this->logUploadDiagnostics('Upload exceeded post_max_size');

            return __('Validation.post max size exceeded', ['limit' => $postLimit]);
        }

        if ($this->bodyWasDiscarded()) {
            $this->logUploadDiagnostics('Upload body discarded before PHP parsed it');

            return __('validation.upload_body_discarded', ['limit' => $postLimit]);
        }

        return null;
    }
    protected function isMultipartRequest(): bool
    {
        return str_contains(strtolower((string) $this->header('content-type')), 'multipart/form-data');
    }
    protected function logUploadDiagnostics(string $reason): void
    {
        Log::warning($reason, [
            'path' => $this->path(),
            'content_length' => (int) $this->server('CONTENT_LENGTH', 0),
            'parsed_fields' => array_keys($this->request->all()),
            'parsed_files' => array_keys($this->files->all()),
            'post_max_size' => (string) ini_get('post_max_size'),
            'upload_max_filesize' => (string) ini_get('upload_max_filesize'),
            'max_file_uploads' => (string) ini_get('max_file_uploads'),
            'file_uploads' => (string) ini_get('file_uploads'),
            'upload_tmp_dir' => (string) ini_get('upload_tmp_dir') ?: sys_get_temp_dir(),
        ]);
    }
    protected function responseFormatter(array $config, mixed $content = null, array $errors = []): JsonResponse
    {
        return response()->json(
            ApiEnvelope::make($config, $content, $errors),
            $config['http_response_code'] ?? 422
        );
    }
    protected function failedValidation(Validator $validator): void
    {
        $discardedUploadMessage = $this->discardedUploadMessage();

        if ($discardedUploadMessage !== null) {
            $validator = $this->uploadFailureValidator($discardedUploadMessage);
        }

        if ($this->is('api/*') || $this->expectsJson()) {
            $response = $this->responseFormatter(
                config: config('response.unprocessable_entity_422'),
                errors: $this->errorList($validator)
            );

            $payload = $response->getData(true);
            $payload['message'] = $validator->errors()->first() ?: $payload['message'];

            throw new HttpResponseException($response->setData($payload));
        }

        parent::failedValidation($validator);
    }
    protected function errorList(Validator $validator): array
    {
        return ApiEnvelope::errorList($validator->errors()->getMessages());
    }

    private function bodyWasDiscarded(): bool
    {
        return (int) $this->server('CONTENT_LENGTH', 0) > 0
            && $this->request->count() === 0
            && $this->files->count() === 0;
    }
    private function uploadFailureValidator(string $message): Validator
    {
        $validator = ValidatorFactory::make([], []);

        $validator->errors()->add('upload', $message);

        return $validator;
    }
}
