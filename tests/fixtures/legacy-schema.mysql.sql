CREATE TABLE `admin` (
  `id` int(3) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(25) NOT NULL,
  `last_name` varchar(25) NOT NULL,
  `email` varchar(255) NOT NULL,
  `mobile1` varchar(15) NOT NULL,
  `mobile2` varchar(15) NOT NULL,
  `password` char(60) NOT NULL,
  `role` char(5) NOT NULL,
  `created_on` datetime NOT NULL,
  `last_login` datetime NOT NULL,
  `last_seen` datetime NOT NULL,
  `last_edited` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `account_status` char(1) NOT NULL DEFAULT '1',
  `deleted` char(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `mobile1` (`mobile1`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=latin1;

CREATE TABLE `backups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_name` varchar(255) CHARACTER SET utf8mb4 NOT NULL,
  `file_url` varchar(255) CHARACTER SET utf8mb4 NOT NULL,
  `date` timestamp NOT NULL DEFAULT current_timestamp(),
  `online` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb3;

CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) CHARACTER SET utf8mb4 NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 NOT NULL,
  `created_on` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=latin1;

CREATE TABLE `couts` (
  `coutsId` int(11) NOT NULL AUTO_INCREMENT,
  `montant` decimal(10,2) NOT NULL,
  `motif` text NOT NULL,
  `author` varchar(255) NOT NULL,
  `date_sortie` date NOT NULL,
  `staffId` bigint(20) NOT NULL,
  `created_on` datetime NOT NULL,
  `lastUpdate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted` int(11) NOT NULL,
  PRIMARY KEY (`coutsId`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;

CREATE TABLE `eventlog` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event` varchar(200) NOT NULL,
  `eventRowIdOrRef` varchar(20) DEFAULT NULL,
  `eventDesc` text DEFAULT NULL,
  `eventTable` varchar(20) DEFAULT NULL,
  `staffInCharge` bigint(20) unsigned NOT NULL,
  `eventTime` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=latin1;

CREATE TABLE `item_category` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_item` bigint(20) NOT NULL,
  `id_category` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_item` (`id_item`),
  KEY `id_category` (`id_category`),
  CONSTRAINT `item_category_ibfk_1` FOREIGN KEY (`id_category`) REFERENCES `categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb3;

CREATE TABLE `items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `code` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `unitPrice` decimal(10,2) NOT NULL,
  `quantity` int(6) NOT NULL,
  `dateAdded` datetime NOT NULL,
  `lastUpdated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `min` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=latin1;

CREATE TABLE `lk_sess` (
  `id` varchar(40) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `timestamp` int(10) unsigned NOT NULL DEFAULT 0,
  `data` blob NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `transactions` (
  `transId` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ref` varchar(10) NOT NULL,
  `itemName` varchar(50) NOT NULL,
  `itemCode` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `quantity` int(6) NOT NULL,
  `unitPrice` decimal(10,2) NOT NULL,
  `totalPrice` decimal(10,2) NOT NULL,
  `totalMoneySpent` decimal(10,2) NOT NULL,
  `amountTendered` decimal(10,2) NOT NULL,
  `discount_amount` decimal(10,2) NOT NULL,
  `discount_percentage` decimal(10,2) NOT NULL,
  `vatPercentage` decimal(10,2) NOT NULL,
  `vatAmount` decimal(10,2) NOT NULL,
  `changeDue` decimal(10,2) NOT NULL,
  `modeOfPayment` varchar(20) NOT NULL,
  `cust_name` varchar(20) DEFAULT NULL,
  `cust_phone` varchar(15) DEFAULT NULL,
  `cust_email` varchar(50) DEFAULT NULL,
  `transType` char(1) NOT NULL,
  `staffId` bigint(20) unsigned NOT NULL,
  `transDate` datetime NOT NULL,
  `lastUpdated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cancelled` char(1) NOT NULL DEFAULT '0',
  `pos` int(11) DEFAULT NULL,
  `cash` int(11) DEFAULT NULL,
  PRIMARY KEY (`transId`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=latin1;
