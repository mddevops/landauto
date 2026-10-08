<?php

namespace App\Templates;

use App\Blocks\OfficialBlockCatalog;
use App\Enums\BlockActionType;
use App\Enums\BlockOwnerScope;
use App\Enums\FormFieldType;
use App\Models\BlockInstance;
use App\Models\Form;
use App\Models\FormField;
use App\Models\Popup;
use App\Models\Site;

/**
 * Lead form of a quiz / chat Site (P9-016, P9-017). Templates cannot own Forms or Popups, so after
 * such a Template is installed the new Site gets its own lead Form in a Popup, opened by every quiz
 * or chat Block whose button has no action yet. Both stay ordinary Site-owned entities.
 */
final class ProvisionLeadPopup
{
    public const QUIZ_FORM_NAME = 'Заявка с квиза';

    public const CHAT_FORM_NAME = 'Заявка из чата';

    public function provision(Site $site, string $formName): void
    {
        $blocks = BlockInstance::query()
            ->whereHas('page', fn ($query) => $query->where('site_id', $site->id))
            ->whereHas('version.definition', fn ($query) => $query
                ->whereIn('slug', OfficialBlockCatalog::ANSWER_SLUGS)
                ->where('owner_scope', BlockOwnerScope::Platform->value))
            ->orderBy('id')
            ->get();

        $popup = null;

        foreach ($blocks as $block) {
            $state = $block->state_json;
            $button = is_array($state['button'] ?? null) ? $state['button'] : [];

            if (is_array($button['action'] ?? null) && is_string($button['action']['type'] ?? null)) {
                continue;
            }

            $popup ??= $this->createPopup($site, $formName);
            $state['button'] = [...$button, 'action' => ['type' => BlockActionType::OpenPopup->value, 'popup' => $popup->public_id]];
            $block->update(['state_json' => $state]);
        }
    }

    private function createPopup(Site $site, string $formName): Popup
    {
        $form = new Form([
            'name' => $formName,
            'status' => true,
            'submit_label' => 'Получить подборку',
            'success_message' => 'Спасибо! Менеджер свяжется с вами и пришлёт подборку.',
        ]);
        $form->site()->associate($site)->save();

        foreach ([['name', FormFieldType::Text, 'Имя', false], ['phone', FormFieldType::Phone, 'Телефон', true]] as $index => [$key, $type, $label, $required]) {
            $field = new FormField(['label' => $label, 'required' => $required, 'sort_order' => $index]);
            $field->key = $key;
            $field->type = $type;
            $field->form()->associate($form)->save();
        }

        $popup = new Popup([
            'name' => $formName,
            'status' => true,
            'title' => 'Получите подборку',
            'text' => 'Менеджер подберёт автомобили по вашим ответам.',
        ]);
        $popup->site()->associate($site);
        $popup->form()->associate($form);
        $popup->save();

        return $popup;
    }
}
