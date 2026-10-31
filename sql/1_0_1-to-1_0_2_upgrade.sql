#IfMissingColumn provider_team_care_team_links excluded
ALTER TABLE `provider_team_care_team_links`
    ADD COLUMN `excluded` tinyint(1) NOT NULL DEFAULT 0 AFTER `care_team_member_id`,
    ADD COLUMN `excluded_at` datetime DEFAULT NULL AFTER `excluded`,
    ADD KEY `idx_provider_team_care_link_excluded` (`provider_team_id`, `pid`, `excluded`);
#EndIf
