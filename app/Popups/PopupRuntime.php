<?php

namespace App\Popups;

use App\Forms\FormRuntime;
use App\Models\Popup;
use App\Models\Site;

/**
 * Presentation-only Popup payload for the draft canvas and preview. It carries no internal IDs,
 * routing, delivery or credentials; an attached Form is included only while it is active.
 */
final class PopupRuntime
{
    public function __construct(private FormRuntime $forms) {}

    /**
     * Active Popups of the Site.
     *
     * @return list<array<string, mixed>>
     */
    public function forSite(Site $site): array
    {
        return array_values($site->popups()
            ->active()
            ->with('form.fields')
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn (Popup $popup): array => $this->present($popup))
            ->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function present(Popup $popup): array
    {
        $form = $popup->form;

        return [
            'public_id' => $popup->public_id,
            'name' => $popup->name,
            'title' => $popup->title,
            'text' => $popup->text,
            'size' => $popup->size->value,
            'animation' => $popup->animation->value,
            'close_on_overlay' => $popup->close_on_overlay,
            'close_on_escape' => $popup->close_on_escape,
            'show_close_button' => $popup->show_close_button,
            'mobile_fullscreen' => $popup->mobile_fullscreen,
            'form' => $form !== null && $form->status ? $this->forms->present($form) : null,
        ];
    }
}
