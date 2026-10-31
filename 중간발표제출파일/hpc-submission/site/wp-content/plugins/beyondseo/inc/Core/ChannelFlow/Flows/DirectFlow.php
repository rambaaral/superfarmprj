<?php
namespace RankingCoach\Inc\Core\ChannelFlow\Flows;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\ChannelFlow\Channels\ChannelInterface;
use RankingCoach\Inc\Core\ChannelFlow\FlowState;

final class DirectFlow implements ChannelInterface {
    public function getNextStep(FlowState $state): array
    {
        // Check for IONOS users and skip direct flow
        if (strtolower(trim(get_option('ionos_group_brand', ''))) === 'ionos') {
            return [
                'step' => 'done',
                'description' => 'IONOS user detected, skipping direct flow.'
            ];
        }

        // Step 1: If not registered, go to registration
        if (!$state->registered) {
            return [
                'step' => 'register',
                'description' => 'User needs to register their account.'
            ];
        }

        // Step 2: If registered but email not verified, wait for email validation
        if (!$state->emailVerified) {
            return [
                'step' => 'email_validation',
                'description' => 'Waiting for email verification.'
            ];
        }

        // Step 3: If email verified but not activated, the activation happens automatically
        // For DIRECT channel, activation is part of the registration finalization
        // So we should proceed to onboarding, not back to register!
        //if (!$state->activated) {
        //    return [
        //        'step' => 'finalizing',
        //        'description' => 'Finalizing account setup...'
        //    ];
        //}

        // Step 4: If activated but not onboarded, go to onboarding
        if (!$state->onboarded) {
            return [
                'step' => 'onboarding',
                'description' => 'Please complete the onboarding process.'
            ];
        }

        // Step 5: All done
        return [
            'step' => 'done',
            'description' => 'All Direct-channel steps completed.'
        ];
    }
}