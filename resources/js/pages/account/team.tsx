import { Head, usePage } from '@inertiajs/react';
import { TeamPage, TeamProvider } from '@splicewire/beam-accounts';
import type { TeamServices } from '@splicewire/beam-accounts';
import { useMemo } from 'react';
import { toast } from 'sonner';
import { teamClient } from '@/lib/accounts-api';

/**
 * The ACCOUNT-realm team surface — members, roles and invitations.
 *
 * The page body is `@splicewire/beam-accounts`' own <TeamPage>: the merged member/invitation roster,
 * the invite dialog, the inline role select, and the remove/revoke/resend confirmations. This file
 * supplies the transport, the feedback sink, and the one fact the package cannot read for itself —
 * WHO is looking, which is what decides the owner-only lens.
 *
 * ⚠️ This replaces the tenant frame console's `/members` and `/invitations` leaves, which have been
 * removed from `config('frame.realms')['tenant']`. Read that file's realm comment: the console's
 * "New invitation" button was a DEAD CONTROL there — `invitations` declares `showable: false,
 * editable: false`, so it has no `/:id` record twin, and the console's list override derives the
 * toolbar's `onNew` from the same `onOpen` it only passes when a twin exists.
 */
export default function AccountTeam() {
    const page = usePage<{ auth: { user: { id: number | string } | null } }>();
    // The package asks for the current principal's id as a STRING and compares it against the
    // roster's own ids; a bigint-keyed host shares a number here, so the cast is the contract rather
    // than defensive noise.
    const currentUserId = page.props.auth.user
        ? String(page.props.auth.user.id)
        : null;

    const services = useMemo<TeamServices>(
        () => ({
            client: teamClient,
            notify: (event) =>
                event.type === 'success'
                    ? toast.success(event.message)
                    : toast.error(event.message),
            onError: (error) =>
                toast.error(
                    error instanceof Error
                        ? error.message
                        : 'Something went wrong.',
                ),
        }),
        [],
    );

    return (
        <>
            <Head title="Team" />
            <div className="p-6">
                <TeamProvider services={services}>
                    {/* `teams.create` (splicewire/laravel-beam-accounts `routes/teams.php`). */}
                    <TeamPage
                        currentUserId={currentUserId}
                        createTeamHref="/teams/create"
                    />
                </TeamProvider>
            </div>
        </>
    );
}
