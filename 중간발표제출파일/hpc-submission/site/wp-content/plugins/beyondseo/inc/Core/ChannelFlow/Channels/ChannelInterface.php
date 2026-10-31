<?php
namespace RankingCoach\Inc\Core\ChannelFlow\Channels;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\ChannelFlow\FlowState;

interface ChannelInterface {
    public function getNextStep(FlowState $state): array;
}