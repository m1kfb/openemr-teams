#IfNotTable provider_team_care_team_links
CREATE TABLE `provider_team_care_team_links` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `provider_team_id` bigint(20) NOT NULL,
    `pid` bigint(20) NOT NULL,
    `user_id` bigint(20) NOT NULL,
    `care_team_member_id` bigint(20) NOT NULL,
    `excluded` tinyint(1) NOT NULL DEFAULT 0,
    `excluded_at` datetime DEFAULT NULL,
    `created_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_provider_team_care_link` (`provider_team_id`, `pid`, `user_id`),
    KEY `idx_provider_team_care_link_member` (`care_team_member_id`),
    CONSTRAINT `fk_provider_team_care_link_team` FOREIGN KEY (`provider_team_id`) REFERENCES `provider_teams` (`id`),
    CONSTRAINT `fk_provider_team_care_link_pid` FOREIGN KEY (`pid`) REFERENCES `patient_data` (`pid`),
    CONSTRAINT `fk_provider_team_care_link_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB;
#EndIf
