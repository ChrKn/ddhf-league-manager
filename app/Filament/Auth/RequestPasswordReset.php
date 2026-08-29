<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BasePage;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Password;

/**
 * The page that asks for an address, answering the same way whether or not it knows it.
 *
 * Filament tells an unknown address apart from a known one twice over: by wording, and by showing
 * a red notification instead of a green one. Either is enough to work out which addresses have an
 * account here, which is not something a page in front of a login should be willing to say. The
 * wording is already dealt with - `passwords.user` and `passwords.sent` say the same sentence in
 * lang/de - and this deals with the rest.
 *
 * Being throttled is deliberately still its own answer. Somebody who is being asked to wait has to
 * be told, or they sit waiting for a message that was never sent.
 *
 * Kept out of app/Filament/Pages on purpose: the panel discovers everything in there and would
 * hang this in the navigation.
 */
class RequestPasswordReset extends BasePage
{
    protected function getFailureNotification(string $status): ?Notification
    {
        if ($status === Password::INVALID_USER) {
            return $this->getSentNotification(Password::RESET_LINK_SENT);
        }

        return parent::getFailureNotification($status);
    }
}
