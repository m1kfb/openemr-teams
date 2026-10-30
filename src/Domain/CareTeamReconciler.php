<?php

/**
 * Pure reconciliation rules for provider-team ↔ patient care-team sync.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Domain;

final class CareTeamReconciler
{
    /**
     * Team → patient: mirror active provider-team staff onto a patient's care team.
     *
     * @param list<int> $teamMemberUserIds Active staff on the provider team
     * @param list<int> $careTeamUserIds Active OpenEMR users on the patient's care team today
     * @param list<int> $moduleTrackedUserIds Users on the care team that this module added for this patient/team
     */
    public function planTeamToPatient(
        array $teamMemberUserIds,
        array $careTeamUserIds,
        array $moduleTrackedUserIds,
    ): CareTeamPatientPlan {
        $teamMemberUserIds = $this->uniquePositive($teamMemberUserIds);
        $careTeamUserIds = $this->uniquePositive($careTeamUserIds);
        $moduleTrackedUserIds = $this->uniquePositive($moduleTrackedUserIds);

        $careSet = array_fill_keys($careTeamUserIds, true);
        $teamSet = array_fill_keys($teamMemberUserIds, true);

        $ensure = [];
        foreach ($teamMemberUserIds as $userId) {
            if (!isset($careSet[$userId])) {
                $ensure[] = $userId;
            }
        }

        $removeModule = [];
        foreach ($moduleTrackedUserIds as $userId) {
            if (!isset($teamSet[$userId]) && isset($careSet[$userId])) {
                $removeModule[] = $userId;
            }
        }

        return new CareTeamPatientPlan($ensure, $removeModule);
    }

    /**
     * Patient → team (explicit opt-in only): adjust provider-team staff from one patient's care team.
     *
     * Adds active users found on the patient's care team who are not yet on the provider team.
     * Removes provider-team staff only when they were module-added on this patient's care team and
     * are no longer on that patient's care team. Manually added care-team members never drive removal.
     *
     * @param list<int> $patientCareTeamUserIds
     * @param list<int> $providerTeamMemberUserIds
     * @param list<int> $moduleLinkedOnPatientUserIds Module-tracked care-team users for this patient
     */
    public function planPatientToTeam(
        array $patientCareTeamUserIds,
        array $providerTeamMemberUserIds,
        array $moduleLinkedOnPatientUserIds,
    ): CareTeamTeamPlan {
        $patientCareTeamUserIds = $this->uniquePositive($patientCareTeamUserIds);
        $providerTeamMemberUserIds = $this->uniquePositive($providerTeamMemberUserIds);
        $moduleLinkedOnPatientUserIds = $this->uniquePositive($moduleLinkedOnPatientUserIds);

        $patientSet = array_fill_keys($patientCareTeamUserIds, true);
        $teamSet = array_fill_keys($providerTeamMemberUserIds, true);

        $addToTeam = [];
        foreach ($patientCareTeamUserIds as $userId) {
            if (!isset($teamSet[$userId])) {
                $addToTeam[] = $userId;
            }
        }

        $removeFromTeam = [];
        foreach ($moduleLinkedOnPatientUserIds as $userId) {
            if (!isset($patientSet[$userId]) && isset($teamSet[$userId])) {
                $removeFromTeam[] = $userId;
            }
        }

        return new CareTeamTeamPlan($addToTeam, $removeFromTeam);
    }

    /**
     * @param list<int> $ids
     * @return list<int>
     */
    private function uniquePositive(array $ids): array
    {
        $seen = [];
        $out = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $out[] = $id;
        }
        return $out;
    }
}
