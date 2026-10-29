#IfNotTable provider_teams
CREATE TABLE `provider_teams` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `active` tinyint(1) NOT NULL DEFAULT 1,
    `notes` text,
    `created_at` datetime NOT NULL,
    `updated_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_provider_teams_active_name` (`active`, `name`)
) ENGINE=InnoDB;
#EndIf

#IfNotTable provider_team_members
CREATE TABLE `provider_team_members` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `team_id` bigint(20) NOT NULL,
    `user_id` bigint(20) NOT NULL,
    `active` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` datetime NOT NULL,
    `updated_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_provider_team_member` (`team_id`, `user_id`),
    KEY `idx_provider_team_members_user` (`user_id`),
    CONSTRAINT `fk_provider_team_members_team` FOREIGN KEY (`team_id`) REFERENCES `provider_teams` (`id`),
    CONSTRAINT `fk_provider_team_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB;
#EndIf

#IfNotTable provider_team_patients
CREATE TABLE `provider_team_patients` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `team_id` bigint(20) NOT NULL,
    `pid` bigint(20) NOT NULL,
    `position` int(11) NOT NULL DEFAULT 0,
    `active` tinyint(1) NOT NULL DEFAULT 1,
    `assigned_at` datetime NOT NULL,
    `unassigned_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_provider_team_patient` (`team_id`, `pid`),
    KEY `idx_provider_team_patients_pid_active` (`pid`, `active`),
    KEY `idx_provider_team_patients_team_active` (`team_id`, `active`, `position`),
    CONSTRAINT `fk_provider_team_patients_team` FOREIGN KEY (`team_id`) REFERENCES `provider_teams` (`id`),
    CONSTRAINT `fk_provider_team_patients_pid` FOREIGN KEY (`pid`) REFERENCES `patient_data` (`pid`)
) ENGINE=InnoDB;
#EndIf

#IfNotTable provider_team_day_staff
CREATE TABLE `provider_team_day_staff` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `team_id` bigint(20) NOT NULL,
    `visit_date` date NOT NULL,
    `user_id` bigint(20) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_provider_team_day_staff` (`team_id`, `visit_date`, `user_id`),
    KEY `idx_provider_team_day_staff_date` (`visit_date`),
    CONSTRAINT `fk_provider_team_day_staff_team` FOREIGN KEY (`team_id`) REFERENCES `provider_teams` (`id`),
    CONSTRAINT `fk_provider_team_day_staff_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB;
#EndIf

#IfNotTable provider_team_visits
CREATE TABLE `provider_team_visits` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `team_id` bigint(20) NOT NULL,
    `pid` bigint(20) NOT NULL,
    `visit_date` date NOT NULL,
    `period` varchar(16) NOT NULL,
    `time_in` smallint(6) DEFAULT NULL,
    `time_out` smallint(6) DEFAULT NULL,
    `status` varchar(16) NOT NULL,
    `recorded_by` bigint(20) DEFAULT NULL,
    `updated_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_provider_team_visit` (`team_id`, `pid`, `visit_date`, `period`),
    KEY `idx_provider_team_visits_team_date` (`team_id`, `visit_date`),
    CONSTRAINT `fk_provider_team_visits_team` FOREIGN KEY (`team_id`) REFERENCES `provider_teams` (`id`),
    CONSTRAINT `fk_provider_team_visits_pid` FOREIGN KEY (`pid`) REFERENCES `patient_data` (`pid`),
    CONSTRAINT `fk_provider_team_visits_recorder` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB;
#EndIf
