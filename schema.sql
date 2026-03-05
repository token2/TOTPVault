
--
-- Table structure for table `magic_links`
--

CREATE TABLE `magic_links` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `otp_profiles`
--

CREATE TABLE `otp_profiles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `issuer` varchar(255) DEFAULT '',
  `secret_encrypted` text NOT NULL COMMENT 'AES-256-GCM encrypted base32 secret',
  `algorithm` enum('SHA1','SHA256','SHA512') DEFAULT 'SHA1',
  `digits` tinyint(4) DEFAULT 6 COMMENT '6, 8 or 10',
  `period` smallint(6) DEFAULT 30 COMMENT 'seconds',
  `color` varchar(7) DEFAULT '#6366f1',
  `icon` varchar(50) DEFAULT 'shield',
  `hide_code` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `profile_shares`
--

CREATE TABLE `profile_shares` (
  `id` int(11) NOT NULL,
  `profile_id` int(11) NOT NULL,
  `shared_by_user_id` int(11) NOT NULL,
  `shared_with_email` varchar(255) NOT NULL,
  `shared_with_user_id` int(11) DEFAULT NULL,
  `can_edit` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `avatar_url` varchar(500) DEFAULT NULL,
  `google_id` varchar(255) DEFAULT NULL,
  `microsoft_id` varchar(255) DEFAULT NULL,
  `github_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
