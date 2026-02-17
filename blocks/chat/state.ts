/**
 * State management for the chat block.
 *
 * @package Spawn
 */

import type { SpawnChatState, SpawnState } from './types';

export function determineState( state: SpawnState ): SpawnChatState {
	if ( ! state.isAuthenticated ) {
		return 'unauthenticated';
	}
	if ( ! state.serverReady ) {
		return 'provisioning';
	}
	if ( state.billingType === 'comped' || state.customerId === 0 ) {
		return 'chat';
	}
	if ( state.creditBalance <= 0 ) {
		return 'no-credits';
	}
	return 'chat';
}