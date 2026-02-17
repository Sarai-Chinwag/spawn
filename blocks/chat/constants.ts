/**
 * Constants for the chat block.
 *
 * @package Spawn
 */

export const API = {
	generateTitle: '/spawn/v1/chat/generate-title',
	balance: '/spawn/v1/credits/balance',
	purchase: '/spawn/v1/credits/purchase',
};

export const STORAGE = {
	session: 'spawn_webchat_session',
	titles: 'spawn_session_titles',
};

export const LOADING_VERBS = [
	'Conjuring', 'Channeling', 'Manifesting', 'Divining', 'Meditating on',
	'Brewing', 'Hatching', 'Nesting on', 'Perching on', 'Pondering',
	'Musing about', 'Wondering about', 'Enchanting', 'Cultivating',
	'Blooming', 'Unfurling', 'Crystallizing', 'Dreaming up', 'Gazing into',
	'Communing with',
];

export const WORD_BANK = [
	'curious', 'mystical', 'cosmic', 'enchanted', 'wandering',
	'dreaming', 'starlit', 'moonlit', 'crystal', 'golden',
	'whispering', 'dancing', 'glowing', 'hidden', 'sacred',
	'crow', 'sparrow', 'phoenix', 'butterfly', 'firefly',
	'musing', 'wonder', 'quest', 'journey', 'vision',
	'bloom', 'garden', 'river', 'mountain', 'star',
	'feather', 'sunflower', 'twilight', 'aurora', 'ember',
];