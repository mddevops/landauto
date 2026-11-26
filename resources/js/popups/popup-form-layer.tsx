import type { CaptchaConfig } from '@/blocks/captcha';
import type { DesignTokens } from '@/blocks/design';
import { FormView } from '@/blocks/form';
import { currentTracking, submitForm } from '@/blocks/form-submit';
import { PopupView } from '@/blocks/popup';
import type { TriggerContextValue } from '@/blocks/trigger-context';
import type { OpenedPopup } from '@/popups/use-site-popups';

export function PopupFormLayer({
    opened,
    design,
    pagePublicId,
    formAction,
    captcha = null,
    authenticated = false,
    onClose,
    onFormEvent,
}: {
    opened: OpenedPopup;
    design: DesignTokens;
    pagePublicId: string;
    formAction: (formId: string) => string;
    captcha?: CaptchaConfig | null;
    authenticated?: boolean;
    onClose: () => void;
    onFormEvent?: (
        event: 'start' | 'validation_error' | 'submit' | 'success',
        context: TriggerContextValue,
    ) => void;
}) {
    const openedForm = opened.popup.form;

    return (
        <PopupView
            popup={opened.popup}
            tokens={design}
            open
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
            returnFocusTo={opened.trigger}
        >
            {openedForm && (
                <FormView
                    form={openedForm}
                    captcha={captcha}
                    confirmation={opened.popup.launch?.confirmation}
                    onEvent={(event) => onFormEvent?.(event, opened.context)}
                    onSubmit={async (values, meta) => {
                        onFormEvent?.('submit', opened.context);

                        const result = await submitForm(
                            formAction(openedForm.public_id),
                            {
                                fields: values,
                                lf_hp: meta.honeypot,
                                ...(meta.captchaToken !== null && {
                                    captcha_token: meta.captchaToken,
                                }),
                                context: {
                                    ...opened.context,
                                    page: pagePublicId,
                                    popup: opened.popup.public_id,
                                },
                                tracking: currentTracking(),
                            },
                            authenticated ? { authenticated: true } : undefined,
                        );

                        if (result.ok) {
                            onFormEvent?.('success', opened.context);
                        }

                        return result;
                    }}
                />
            )}
        </PopupView>
    );
}
