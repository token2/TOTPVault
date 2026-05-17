-- Add generic OAuth/OIDC identity mapping for existing installations.
-- Fresh installs are covered by schema.sql.

CREATE TABLE IF NOT EXISTS `oauth_identities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `provider` varchar(64) NOT NULL,
  `provider_id` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE INDEX `uniq_provider_identity` (`provider`, `provider_id`),
  INDEX `idx_oauth_identities_user_id` (`user_id`),
  CONSTRAINT `fk_oauth_identities_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `oauth_identities` (`user_id`, `provider`, `provider_id`)
SELECT `id`, 'google', `google_id`
FROM `users`
WHERE `google_id` IS NOT NULL AND `google_id` <> '';

INSERT IGNORE INTO `oauth_identities` (`user_id`, `provider`, `provider_id`)
SELECT `id`, 'microsoft', `microsoft_id`
FROM `users`
WHERE `microsoft_id` IS NOT NULL AND `microsoft_id` <> '';

INSERT IGNORE INTO `oauth_identities` (`user_id`, `provider`, `provider_id`)
SELECT `id`, 'github', `github_id`
FROM `users`
WHERE `github_id` IS NOT NULL AND `github_id` <> '';
