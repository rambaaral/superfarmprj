import { ctaActionFor } from './cta-action';
import { templates } from './templates/registry';

export const SLOTS = {
	ADMIN_DASHBOARD: 'admin-dashboard',
	ADMIN_OTHERS: 'admin-others',
	ADMIN_ASSIST: 'admin-assist',
	ADMIN_MODAL: 'admin-modal',
	ADMIN_TOPBAR: 'admin-topbar',
	AGENT_CHAT: 'agent-chat',
	FRONTEND_BOTTOM: 'frontend-bottom',
	FRONTEND_TOPBAR: 'frontend-topbar',
	FRONTEND_MODAL: 'frontend-modal',
};

// agent-chat is null because the Agent renders it as a chat suggestion.
const TEMPLATES = {
	[SLOTS.ADMIN_DASHBOARD]: 'card',
	[SLOTS.ADMIN_OTHERS]: 'card',
	[SLOTS.ADMIN_ASSIST]: 'card',
	[SLOTS.ADMIN_MODAL]: 'pricing',
	[SLOTS.ADMIN_TOPBAR]: 'pill',
	[SLOTS.AGENT_CHAT]: null,
	[SLOTS.FRONTEND_BOTTOM]: 'bar',
	[SLOTS.FRONTEND_TOPBAR]: 'pill',
	[SLOTS.FRONTEND_MODAL]: 'pricing',
};

export const templateFor = (slot) =>
	templates.find(({ id }) => id === TEMPLATES[slot])?.Template ?? null;

// A trial-block modal is the paywall, so without an offer there is nothing to show.
const hasOfferWhenBlocking = (notification) =>
	notification.trigger !== 'trial-block' || Boolean(notification.offer);

// A pill that acts in place needs no href.
const hasPillAction = (notification, href) =>
	Boolean(notification['cta-label'] && (href || ctaActionFor(notification)));

// Deciding this inside Pill would count a view for a pill that never rendered.
const REQUIREMENTS = {
	[SLOTS.FRONTEND_TOPBAR]: hasPillAction,
	[SLOTS.ADMIN_TOPBAR]: hasPillAction,
	[SLOTS.ADMIN_MODAL]: hasOfferWhenBlocking,
	[SLOTS.FRONTEND_MODAL]: hasOfferWhenBlocking,
};

export const fillsSlot = (slot, notification, href) =>
	REQUIREMENTS[slot]?.(notification, href) ?? true;

// The pill stays as the persistent reminder after the bar or card is dismissed.
const DISMISSAL_EXEMPT = [SLOTS.FRONTEND_TOPBAR, SLOTS.ADMIN_TOPBAR];

export const ignoresDismissal = (slot) => DISMISSAL_EXEMPT.includes(slot);
