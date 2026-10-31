<?php
namespace RankingCoach\Inc\Core\ChannelFlow;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

final class FlowState {
    public function __construct(
        public bool $registered = false,
        public bool $emailVerified = false,
        public bool $onboarded = false,
        public bool $activated = false,
        public array $meta = []
    ) {}

    public static function fromArray(array $data): self {
        return new self(
            $data['registered'] ?? false,
            $data['emailVerified'] ?? false,
            $data['onboarded'] ?? false,
            $data['activated'] ?? false,
            $data['meta'] ?? []
        );
    }

    public function toArray(): array {
        return [
            'registered' => $this->registered,
            'emailVerified' => $this->emailVerified,
            'onboarded' => $this->onboarded,
            'activated' => $this->activated,
            'meta' => $this->meta,
        ];
    }
}