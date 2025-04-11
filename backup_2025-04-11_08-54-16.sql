

DROP TABLE IF EXISTS `admin_users`;

CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;

INSERT INTO `admin_users` VALUES('1','admin','$2y$10$w5UL5pM78.jtWi7BvnL8wOV/V/5vKhF7ux3AHBKqC0K67XEUt7rqG');


DROP TABLE IF EXISTS `config`;

CREATE TABLE `config` (
  `key` varchar(50) NOT NULL,
  `value` varchar(255) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `config` VALUES('max_participants','25');
INSERT INTO `config` VALUES('run_day','4');
INSERT INTO `config` VALUES('run_time','19:00');


DROP TABLE IF EXISTS `registrations`;

CREATE TABLE `registrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `date` date NOT NULL,
  `waitlisted` tinyint(1) NOT NULL DEFAULT '0',
  `registrationTime` datetime NOT NULL,
  `personCount` int(11) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email_date` (`email`,`date`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4;

INSERT INTO `registrations` VALUES('1','André','andre.hinz@stadt-frankfurt.de','','2025-04-17','0','2025-04-10 21:22:29','1');
INSERT INTO `registrations` VALUES('2','Jonas Eisenhauer','jeisenhauer@langen.de','01743184423','2025-04-17','0','2025-04-10 21:57:56','6');
INSERT INTO `registrations` VALUES('3','Florian Erbacher','flerb@flerb.de','01715539988','2025-04-17','0','2025-04-11 04:48:51','1');


DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=latin1;

INSERT INTO `users` VALUES('1','admin','$2y$10$/zQDYJaHM/yiNFv7WhuXyuVUJZGPLt3uFLkUhx8j2Wib2RPJb59xK','2025-04-10 21:19:34');
