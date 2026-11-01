<?php

namespace Tests\Feature;

use App\CentralLogics\Helpers;
use App\Support\Notification\NotificationMessages;
use Tests\TestCase;

class NotificationMessagesTest extends TestCase
{
    public function test_static_messages_keep_their_exact_shape(): void
    {
        $this->assertSame(
            ['title', 'description', 'order_id', 'image', 'type'],
            array_keys(NotificationMessages::verifiedSeller()),
        );

        $this->assertSame('verified_badge', NotificationMessages::verifiedSeller()['type']);
        $this->assertSame('unblock', NotificationMessages::accountActivated()['type']);
        $this->assertSame('block', NotificationMessages::accountSuspended()['type']);
    }

    /**
     * Two keys, one sentence: the customer copy is namespaced 'messages.Account activation' and
     * the store copy is the bare 'Account activation'. They render the same words today, so
     * anyone tidying up is liable to fold them into one -- and the two are translated separately
     * per locale, so folding them silently retranslates the other audience's notification.
     *
     * Cased exactly as the callers write it. This test used to assert a capitalised
     * "Account Activation" that no caller uses, and because translate() appends whatever it is
     * handed to the per-locale messages file as it renders, the failing test kept re-creating
     * its own junk key there.
     */
    public function test_the_two_account_activation_translation_keys_stay_separate(): void
    {
        $this->assertSame(translate('messages.Account activation'), NotificationMessages::accountActivated()['title']);
        $this->assertSame(translate('Account activation'), NotificationMessages::storeAccountActivated()['title']);
    }

    public function test_the_id_key_is_not_forced_to_order_id(): void
    {
        $this->assertSame(
            ['title', 'description', 'trip_id', 'image', 'type'],
            array_keys(NotificationMessages::referralWalletCredit(50, 'Jane Doe', ['trip_id' => 1])),
            'A trip notification must not gain a spurious order_id key.',
        );

        $this->assertSame(
            ['title', 'description', 'order_id', 'image', 'type'],
            array_keys(NotificationMessages::loyaltyPointsEarned(10, ['order_id' => 3])),
        );
    }

    public function test_the_two_cashback_variants_use_different_translation_keys(): void
    {
        $concat = NotificationMessages::cashbackCredited(25, ['order_id' => 1])['title'];
        $placeholder = NotificationMessages::cashbackCreditedWithAmountPlaceholder(25, ['trip_id' => 1])['title'];

        $this->assertSame(translate('messages.Congratulations, you have received :amount', ['amount' => 25]).' '.translate('CashBack'), $concat);
        $this->assertSame(translate('messages.Congratulations, you have received :amount cashback', ['amount' => 25]), $placeholder);

        $this->assertStringNotContainsString(':amount', $concat);
        $this->assertStringNotContainsString(':amount', $placeholder);
    }

    public function test_referral_amounts_are_currency_formatted(): void
    {
        $message = NotificationMessages::referralWalletCredit(50, 'Jane Doe', ['order_id' => 1]);

        $this->assertStringContainsString(Helpers::format_currency(50), $message['description']);
    }

    public function test_delivery_man_referral_bonus_uses_data_id_not_order_id(): void
    {
        $this->assertSame(
            ['title', 'description', 'data_id', 'image', 'type'],
            array_keys(NotificationMessages::deliveryManReferralBonus(50, 7)),
        );
    }
}
