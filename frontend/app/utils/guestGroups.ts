import type { GuestGroup } from '~/types/api'

/** Every guest group, in the API's order (App\Enums\GuestGroup), for selects and filters. */
export const GUEST_GROUPS: readonly GuestGroup[] = ['groom_family', 'bride_family', 'relatives', 'friends', 'colleagues', 'neighbors', 'other']
