<?php

namespace App\Integrations;

use App\Enums\IntegrationAuthType;
use App\Models\IntegrationProfile;
use App\Models\Workspace;
use App\Support\WorkspaceContext;

/**
 * Workspace-scoped access to Integration Profiles plus the only place credentials are written.
 * Foreign or unknown public IDs are reported as missing.
 */
final class IntegrationProfiles
{
    private const SAFE_TEXT = 'regex:/^[^\x00-\x1F\x7F]+$/';

    public function __construct(private WorkspaceContext $context) {}

    public function workspace(): Workspace
    {
        $workspace = $this->context->current();
        abort_if($workspace === null, 404);

        return $workspace;
    }

    public function find(string $publicId): IntegrationProfile
    {
        return IntegrationProfile::query()
            ->where('workspace_id', $this->workspace()->id)
            ->where('public_id', strtolower($publicId))
            ->where('status', '!=', 'archived')
            ->firstOrFail();
    }

    public function isReferenced(IntegrationProfile $profile): bool
    {
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function present(IntegrationProfile $profile): array
    {
        return [
            'public_id' => $profile->public_id,
            'name' => $profile->name,
            'provider_type' => $profile->provider_type->value,
            'provider_type_label' => $profile->provider_type->label(),
            'provider_key' => $profile->provider_key,
            'base_url' => $profile->base_url,
            'auth_type' => $profile->auth_type->value,
            'auth_type_label' => $profile->auth_type->label(),
            'api_key_header' => $profile->settings_json['api_key_header'] ?? null,
            'status' => $profile->status->value,
            'status_label' => $profile->status->label(),
        ];
    }

    /**
     * Input names never match model attributes, so a validation failure cannot echo a secret.
     *
     * @return array<string, list<string>>
     */
    public function credentialRules(string $authType, bool $required): array
    {
        $presence = $required ? 'required' : 'nullable';

        return match (IntegrationAuthType::tryFrom($authType)) {
            IntegrationAuthType::Bearer, IntegrationAuthType::ApiKeyHeader => [
                'credential_token' => [$presence, 'string', 'max:4096', self::SAFE_TEXT],
            ],
            IntegrationAuthType::Basic => [
                'credential_username' => [$presence, 'string', 'max:255', self::SAFE_TEXT, 'not_regex:/:/'],
                'credential_password' => [$presence, 'string', 'max:1024', self::SAFE_TEXT],
            ],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function writeCredentials(IntegrationProfile $profile, array $input): void
    {
        $credentials = [];

        foreach ($profile->auth_type->credentialKeys() as $key) {
            $credentials[$key] = (string) $input['credential_'.$key];
        }

        $profile->encrypted_credentials = $credentials === [] ? null : $credentials;
    }
}
