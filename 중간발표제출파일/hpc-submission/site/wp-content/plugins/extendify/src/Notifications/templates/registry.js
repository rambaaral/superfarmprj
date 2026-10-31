import { Bar } from './Bar';
import bar from './bar.schema.json';
import { Card } from './Card';
import card from './card.schema.json';
import { Pill } from './Pill';
import { Pricing } from './Pricing';
import pill from './pill.schema.json';
import pricing from './pricing.schema.json';

export const templates = [
	{ id: 'bar', Template: Bar, schema: bar },
	{ id: 'card', Template: Card, schema: card },
	{ id: 'pill', Template: Pill, schema: pill },
	{ id: 'pricing', Template: Pricing, schema: pricing },
];
