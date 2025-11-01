-- MariaDB dump 10.19  Distrib 10.4.28-MariaDB, for osx10.10 (x86_64)
--
-- Host: 127.0.0.1    Database: zenamanage_testing
-- ------------------------------------------------------
-- Server version	10.4.28-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` varchar(255) NOT NULL,
  `action` varchar(255) NOT NULL,
  `entity_type` varchar(255) NOT NULL,
  `entity_id` varchar(255) DEFAULT NULL,
  `project_id` varchar(255) DEFAULT NULL,
  `tenant_id` varchar(255) DEFAULT NULL,
  `old_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_data`)),
  `new_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_data`)),
  `ip_address` varchar(255) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_audit_logs_tenant_created` (`tenant_id`,`created_at`),
  KEY `idx_audit_logs_user_created` (`user_id`,`created_at`),
  KEY `idx_audit_logs_entity` (`entity_type`,`entity_id`),
  KEY `audit_logs_entity_history_index` (`entity_type`,`entity_id`,`created_at`),
  KEY `audit_logs_action_created_index` (`action`,`created_at`),
  KEY `audit_logs_created_at_index` (`created_at`),
  KEY `audit_logs_action_index` (`action`),
  KEY `audit_logs_user_id_index` (`user_id`),
  KEY `audit_logs_tenant_id_index` (`tenant_id`),
  KEY `audit_logs_created_at_action_index` (`created_at`,`action`),
  KEY `audit_logs_created_by_index` (`created_by`),
  KEY `audit_logs_updated_by_index` (`updated_by`),
  CONSTRAINT `audit_logs_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `backup_logs`
--

DROP TABLE IF EXISTS `backup_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(255) NOT NULL,
  `path` varchar(255) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `backup_logs_type_created_at_index` (`type`,`created_at`),
  KEY `backup_logs_created_by_index` (`created_by`),
  KEY `backup_logs_updated_by_index` (`updated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `backup_logs`
--

LOCK TABLES `backup_logs` WRITE;
/*!40000 ALTER TABLE `backup_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `backup_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `billing_invoices`
--

DROP TABLE IF EXISTS `billing_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `billing_invoices` (
  `id` char(26) NOT NULL,
  `tenant_id` char(26) NOT NULL,
  `subscription_id` char(26) DEFAULT NULL,
  `invoice_number` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `tax_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `status` enum('draft','sent','paid','unpaid','overdue','cancelled') NOT NULL DEFAULT 'draft',
  `issue_date` date NOT NULL,
  `due_date` date NOT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `stripe_invoice_id` varchar(255) DEFAULT NULL,
  `stripe_payment_intent_id` varchar(255) DEFAULT NULL,
  `line_items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`line_items`)),
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `billing_invoices_invoice_number_unique` (`invoice_number`),
  KEY `billing_invoices_subscription_id_foreign` (`subscription_id`),
  KEY `billing_invoices_tenant_id_status_index` (`tenant_id`,`status`),
  KEY `billing_invoices_status_due_date_index` (`status`,`due_date`),
  KEY `billing_invoices_issue_date_status_index` (`issue_date`,`status`),
  KEY `billing_invoices_stripe_invoice_id_index` (`stripe_invoice_id`),
  KEY `billing_invoices_created_by_index` (`created_by`),
  KEY `billing_invoices_updated_by_index` (`updated_by`),
  CONSTRAINT `billing_invoices_subscription_id_foreign` FOREIGN KEY (`subscription_id`) REFERENCES `tenant_subscriptions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `billing_invoices_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `billing_invoices`
--

LOCK TABLES `billing_invoices` WRITE;
/*!40000 ALTER TABLE `billing_invoices` DISABLE KEYS */;
/*!40000 ALTER TABLE `billing_invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `billing_payments`
--

DROP TABLE IF EXISTS `billing_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `billing_payments` (
  `id` char(26) NOT NULL,
  `tenant_id` char(26) NOT NULL,
  `invoice_id` char(26) DEFAULT NULL,
  `subscription_id` char(26) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `status` enum('pending','completed','failed','refunded','cancelled') NOT NULL DEFAULT 'pending',
  `payment_method` enum('stripe','paypal','bank_transfer','manual') NOT NULL DEFAULT 'stripe',
  `payment_reference` varchar(255) DEFAULT NULL,
  `stripe_payment_intent_id` varchar(255) DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `billing_payments_invoice_id_foreign` (`invoice_id`),
  KEY `billing_payments_subscription_id_foreign` (`subscription_id`),
  KEY `billing_payments_tenant_id_status_index` (`tenant_id`,`status`),
  KEY `billing_payments_status_processed_at_index` (`status`,`processed_at`),
  KEY `billing_payments_stripe_payment_intent_id_index` (`stripe_payment_intent_id`),
  KEY `billing_payments_created_by_index` (`created_by`),
  KEY `billing_payments_updated_by_index` (`updated_by`),
  CONSTRAINT `billing_payments_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `billing_invoices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `billing_payments_subscription_id_foreign` FOREIGN KEY (`subscription_id`) REFERENCES `tenant_subscriptions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `billing_payments_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `billing_payments`
--

LOCK TABLES `billing_payments` WRITE;
/*!40000 ALTER TABLE `billing_payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `billing_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `billing_plans`
--

DROP TABLE IF EXISTS `billing_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `billing_plans` (
  `id` char(26) NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `monthly_price` decimal(10,2) NOT NULL,
  `yearly_price` decimal(10,2) DEFAULT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `features` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`features`)),
  `max_users` int(11) DEFAULT NULL,
  `max_projects` int(11) DEFAULT NULL,
  `storage_limit_mb` bigint(20) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `billing_plans_slug_unique` (`slug`),
  KEY `billing_plans_is_active_sort_order_index` (`is_active`,`sort_order`),
  KEY `billing_plans_slug_index` (`slug`),
  KEY `billing_plans_created_by_index` (`created_by`),
  KEY `billing_plans_updated_by_index` (`updated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `billing_plans`
--

LOCK TABLES `billing_plans` WRITE;
/*!40000 ALTER TABLE `billing_plans` DISABLE KEYS */;
/*!40000 ALTER TABLE `billing_plans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_entries`
--

DROP TABLE IF EXISTS `cache_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_entries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) NOT NULL,
  `value` longtext NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cache_entries_key_unique` (`key`),
  KEY `cache_entries_expires_at_index` (`expires_at`),
  KEY `cache_entries_key_expires_at_index` (`key`,`expires_at`),
  KEY `cache_entries_created_by_index` (`created_by`),
  KEY `cache_entries_updated_by_index` (`updated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_entries`
--

LOCK TABLES `cache_entries` WRITE;
/*!40000 ALTER TABLE `cache_entries` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_entries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `calendar_events`
--

DROP TABLE IF EXISTS `calendar_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `calendar_events` (
  `id` char(26) NOT NULL,
  `tenant_id` char(26) NOT NULL,
  `user_id` char(26) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `all_day` tinyint(1) NOT NULL DEFAULT 0,
  `project_id` char(26) DEFAULT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'scheduled',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `calendar_events_project_id_foreign` (`project_id`),
  KEY `calendar_events_tenant_id_start_time_index` (`tenant_id`,`start_time`),
  KEY `calendar_events_user_id_start_time_index` (`user_id`,`start_time`),
  CONSTRAINT `calendar_events_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `calendar_events_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `calendar_events_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `calendar_events`
--

LOCK TABLES `calendar_events` WRITE;
/*!40000 ALTER TABLE `calendar_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `calendar_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `calendar_integrations`
--

DROP TABLE IF EXISTS `calendar_integrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `calendar_integrations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` varchar(255) NOT NULL,
  `provider` varchar(255) NOT NULL,
  `calendar_id` varchar(255) DEFAULT NULL,
  `calendar_name` varchar(255) NOT NULL,
  `access_token` text DEFAULT NULL,
  `refresh_token` text DEFAULT NULL,
  `token_expires_at` timestamp NULL DEFAULT NULL,
  `provider_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`provider_data`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sync_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `last_sync_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `calendar_integrations_user_id_provider_index` (`user_id`,`provider`),
  KEY `calendar_integrations_provider_is_active_index` (`provider`,`is_active`),
  KEY `calendar_integrations_created_by_index` (`created_by`),
  KEY `calendar_integrations_updated_by_index` (`updated_by`),
  CONSTRAINT `calendar_integrations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `calendar_integrations`
--

LOCK TABLES `calendar_integrations` WRITE;
/*!40000 ALTER TABLE `calendar_integrations` DISABLE KEYS */;
/*!40000 ALTER TABLE `calendar_integrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `change_request_approvals`
--

DROP TABLE IF EXISTS `change_request_approvals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `change_request_approvals` (
  `id` varchar(255) NOT NULL,
  `change_request_id` varchar(255) NOT NULL,
  `user_id` varchar(255) NOT NULL,
  `level` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `comments` text DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `change_request_approvals_change_request_id_level_index` (`change_request_id`,`level`),
  KEY `change_request_approvals_user_id_status_index` (`user_id`,`status`),
  KEY `change_request_approvals_status_index` (`status`),
  KEY `change_request_approvals_created_by_index` (`created_by`),
  KEY `change_request_approvals_updated_by_index` (`updated_by`),
  CONSTRAINT `change_request_approvals_change_request_id_foreign` FOREIGN KEY (`change_request_id`) REFERENCES `change_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `change_request_approvals_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `change_request_approvals`
--

LOCK TABLES `change_request_approvals` WRITE;
/*!40000 ALTER TABLE `change_request_approvals` DISABLE KEYS */;
/*!40000 ALTER TABLE `change_request_approvals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `change_request_comments`
--

DROP TABLE IF EXISTS `change_request_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `change_request_comments` (
  `id` varchar(255) NOT NULL,
  `change_request_id` varchar(255) NOT NULL,
  `user_id` varchar(255) NOT NULL,
  `comment` text NOT NULL,
  `parent_id` varchar(255) DEFAULT NULL,
  `is_internal` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `change_request_comments_change_request_id_created_at_index` (`change_request_id`,`created_at`),
  KEY `change_request_comments_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `change_request_comments_parent_id_index` (`parent_id`),
  KEY `change_request_comments_created_by_index` (`created_by`),
  KEY `change_request_comments_updated_by_index` (`updated_by`),
  CONSTRAINT `change_request_comments_change_request_id_foreign` FOREIGN KEY (`change_request_id`) REFERENCES `change_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `change_request_comments_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `change_request_comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `change_request_comments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `change_request_comments`
--

LOCK TABLES `change_request_comments` WRITE;
/*!40000 ALTER TABLE `change_request_comments` DISABLE KEYS */;
/*!40000 ALTER TABLE `change_request_comments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `change_requests`
--

DROP TABLE IF EXISTS `change_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `change_requests` (
  `id` varchar(255) NOT NULL,
  `tenant_id` varchar(255) NOT NULL,
  `project_id` varchar(255) NOT NULL,
  `task_id` varchar(255) DEFAULT NULL,
  `change_number` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `change_type` varchar(255) NOT NULL,
  `priority` varchar(255) NOT NULL DEFAULT 'medium',
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `impact_level` varchar(255) NOT NULL DEFAULT 'low',
  `requested_by` varchar(255) NOT NULL,
  `assigned_to` varchar(255) DEFAULT NULL,
  `approved_by` varchar(255) DEFAULT NULL,
  `rejected_by` varchar(255) DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `due_date` date DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `implemented_at` timestamp NULL DEFAULT NULL,
  `estimated_cost` decimal(15,2) NOT NULL DEFAULT 0.00,
  `actual_cost` decimal(15,2) NOT NULL DEFAULT 0.00,
  `estimated_days` int(11) NOT NULL DEFAULT 0,
  `actual_days` int(11) NOT NULL DEFAULT 0,
  `approval_notes` text DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `implementation_notes` text DEFAULT NULL,
  `attachments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attachments`)),
  `impact_analysis` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`impact_analysis`)),
  `risk_assessment` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`risk_assessment`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `change_requests_change_number_unique` (`change_number`),
  KEY `change_requests_task_id_foreign` (`task_id`),
  KEY `change_requests_approved_by_foreign` (`approved_by`),
  KEY `change_requests_rejected_by_foreign` (`rejected_by`),
  KEY `change_requests_tenant_id_status_index` (`tenant_id`,`status`),
  KEY `change_requests_project_id_status_index` (`project_id`,`status`),
  KEY `change_requests_requested_by_created_at_index` (`requested_by`,`created_at`),
  KEY `change_requests_assigned_to_status_index` (`assigned_to`,`status`),
  KEY `change_requests_status_priority_index` (`status`,`priority`),
  KEY `change_requests_due_date_index` (`due_date`),
  KEY `change_requests_change_number_index` (`change_number`),
  KEY `change_requests_project_status_index` (`project_id`,`status`),
  KEY `change_requests_requester_status_index` (`requested_by`,`status`),
  KEY `change_requests_priority_status_index` (`priority`,`status`),
  KEY `change_requests_created_by_index` (`created_by`),
  KEY `change_requests_updated_by_index` (`updated_by`),
  CONSTRAINT `change_requests_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `change_requests_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `change_requests_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `change_requests_rejected_by_foreign` FOREIGN KEY (`rejected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `change_requests_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `change_requests_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `change_requests_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `change_requests`
--

LOCK TABLES `change_requests` WRITE;
/*!40000 ALTER TABLE `change_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `change_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clients`
--

DROP TABLE IF EXISTS `clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clients` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` char(26) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `lifecycle_stage` enum('lead','prospect','customer','inactive') NOT NULL DEFAULT 'lead',
  `notes` text DEFAULT NULL,
  `address` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`address`)),
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `clients_tenant_id_lifecycle_stage_index` (`tenant_id`,`lifecycle_stage`),
  KEY `clients_tenant_id_email_index` (`tenant_id`,`email`),
  KEY `clients_tenant_id_created_at_index` (`tenant_id`,`created_at`),
  KEY `clients_tenant_id_index` (`tenant_id`),
  KEY `clients_created_by_index` (`created_by`),
  KEY `clients_updated_by_index` (`updated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clients`
--

LOCK TABLES `clients` WRITE;
/*!40000 ALTER TABLE `clients` DISABLE KEYS */;
/*!40000 ALTER TABLE `clients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `components`
--

DROP TABLE IF EXISTS `components`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `components` (
  `id` varchar(255) NOT NULL,
  `tenant_id` varchar(255) DEFAULT NULL,
  `project_id` varchar(255) NOT NULL,
  `parent_component_id` varchar(255) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'component',
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `priority` varchar(255) NOT NULL DEFAULT 'medium',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `budget` decimal(15,2) NOT NULL DEFAULT 0.00,
  `dependencies` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dependencies`)),
  `created_by` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `progress_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `planned_cost` decimal(15,2) NOT NULL DEFAULT 0.00,
  `actual_cost` decimal(15,2) NOT NULL DEFAULT 0.00,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `components_project_id_status_index` (`project_id`,`status`),
  KEY `components_project_id_sort_order_index` (`project_id`,`sort_order`),
  KEY `components_parent_component_id_index` (`parent_component_id`),
  KEY `components_project_status_index` (`project_id`,`status`),
  KEY `components_type_status_index` (`type`,`status`),
  KEY `components_tenant_id_index` (`tenant_id`),
  KEY `components_priority_index` (`priority`),
  KEY `components_start_date_index` (`start_date`),
  KEY `components_end_date_index` (`end_date`),
  KEY `components_created_by_foreign` (`created_by`),
  KEY `components_updated_by_index` (`updated_by`),
  CONSTRAINT `components_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `components_parent_component_id_foreign` FOREIGN KEY (`parent_component_id`) REFERENCES `components` (`id`) ON DELETE CASCADE,
  CONSTRAINT `components_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `components_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `components`
--

LOCK TABLES `components` WRITE;
/*!40000 ALTER TABLE `components` DISABLE KEYS */;
/*!40000 ALTER TABLE `components` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dashboard_alerts`
--

DROP TABLE IF EXISTS `dashboard_alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dashboard_alerts` (
  `id` char(26) NOT NULL,
  `user_id` char(26) NOT NULL,
  `tenant_id` char(26) NOT NULL,
  `message` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'info',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dashboard_alerts_user_id_is_read_index` (`user_id`,`is_read`),
  KEY `dashboard_alerts_tenant_id_created_at_index` (`tenant_id`,`created_at`),
  CONSTRAINT `dashboard_alerts_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dashboard_alerts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dashboard_alerts`
--

LOCK TABLES `dashboard_alerts` WRITE;
/*!40000 ALTER TABLE `dashboard_alerts` DISABLE KEYS */;
/*!40000 ALTER TABLE `dashboard_alerts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dashboard_metric_values`
--

DROP TABLE IF EXISTS `dashboard_metric_values`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dashboard_metric_values` (
  `id` char(26) NOT NULL,
  `metric_id` char(26) NOT NULL,
  `project_id` char(26) DEFAULT NULL,
  `tenant_id` char(26) NOT NULL,
  `value` decimal(15,4) NOT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dashboard_metric_values_tenant_id_foreign` (`tenant_id`),
  KEY `dashboard_metric_values_metric_id_recorded_at_index` (`metric_id`,`recorded_at`),
  KEY `dashboard_metric_values_project_id_tenant_id_recorded_at_index` (`project_id`,`tenant_id`,`recorded_at`),
  CONSTRAINT `dashboard_metric_values_metric_id_foreign` FOREIGN KEY (`metric_id`) REFERENCES `dashboard_metrics` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dashboard_metric_values_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dashboard_metric_values_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dashboard_metric_values`
--

LOCK TABLES `dashboard_metric_values` WRITE;
/*!40000 ALTER TABLE `dashboard_metric_values` DISABLE KEYS */;
/*!40000 ALTER TABLE `dashboard_metric_values` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dashboard_metrics`
--

DROP TABLE IF EXISTS `dashboard_metrics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dashboard_metrics` (
  `id` char(26) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `unit` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `category` varchar(255) DEFAULT NULL,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `project_id` char(26) DEFAULT NULL,
  `tenant_id` char(26) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dashboard_metrics_is_active_category_index` (`is_active`,`category`),
  KEY `dashboard_metrics_project_id_tenant_id_index` (`project_id`,`tenant_id`),
  KEY `dashboard_metrics_tenant_id_foreign` (`tenant_id`),
  CONSTRAINT `dashboard_metrics_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dashboard_metrics_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dashboard_metrics`
--

LOCK TABLES `dashboard_metrics` WRITE;
/*!40000 ALTER TABLE `dashboard_metrics` DISABLE KEYS */;
/*!40000 ALTER TABLE `dashboard_metrics` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dashboard_widget_data_cache`
--

DROP TABLE IF EXISTS `dashboard_widget_data_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dashboard_widget_data_cache` (
  `id` char(26) NOT NULL,
  `widget_id` char(26) DEFAULT NULL,
  `user_id` char(26) DEFAULT NULL,
  `project_id` char(26) DEFAULT NULL,
  `tenant_id` char(26) NOT NULL,
  `cache_key` varchar(255) NOT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`data`)),
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dashboard_widget_data_cache_user_id_foreign` (`user_id`),
  KEY `dashboard_widget_data_cache_project_id_foreign` (`project_id`),
  KEY `dashboard_widget_data_cache_tenant_id_foreign` (`tenant_id`),
  KEY `dashboard_widget_data_cache_cache_key_expires_at_index` (`cache_key`,`expires_at`),
  KEY `dashboard_widget_data_cache_widget_id_user_id_tenant_id_index` (`widget_id`,`user_id`,`tenant_id`),
  CONSTRAINT `dashboard_widget_data_cache_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dashboard_widget_data_cache_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dashboard_widget_data_cache_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dashboard_widget_data_cache_widget_id_foreign` FOREIGN KEY (`widget_id`) REFERENCES `dashboard_widgets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dashboard_widget_data_cache`
--

LOCK TABLES `dashboard_widget_data_cache` WRITE;
/*!40000 ALTER TABLE `dashboard_widget_data_cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `dashboard_widget_data_cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dashboard_widgets`
--

DROP TABLE IF EXISTS `dashboard_widgets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dashboard_widgets` (
  `id` char(26) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `category` varchar(255) NOT NULL,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `data_source` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data_source`)),
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dashboard_widgets_type_category_index` (`type`,`category`),
  KEY `dashboard_widgets_is_active_index` (`is_active`),
  KEY `dashboard_widgets_created_by_index` (`created_by`),
  KEY `dashboard_widgets_updated_by_index` (`updated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dashboard_widgets`
--

LOCK TABLES `dashboard_widgets` WRITE;
/*!40000 ALTER TABLE `dashboard_widgets` DISABLE KEYS */;
/*!40000 ALTER TABLE `dashboard_widgets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dashboards`
--

DROP TABLE IF EXISTS `dashboards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dashboards` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `user_id` char(26) NOT NULL,
  `tenant_id` char(26) NOT NULL,
  `widget_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`widget_config`)),
  `layout` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`layout`)),
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dashboards_tenant_id_foreign` (`tenant_id`),
  KEY `dashboards_user_id_tenant_id_index` (`user_id`,`tenant_id`),
  CONSTRAINT `dashboards_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dashboards_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dashboards`
--

LOCK TABLES `dashboards` WRITE;
/*!40000 ALTER TABLE `dashboards` DISABLE KEYS */;
/*!40000 ALTER TABLE `dashboards` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `data_retention_policies`
--

DROP TABLE IF EXISTS `data_retention_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `data_retention_policies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `table_name` varchar(255) NOT NULL,
  `retention_period` varchar(255) NOT NULL,
  `retention_type` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `data_retention_policies_table_name_unique` (`table_name`),
  KEY `data_retention_policies_is_active_retention_type_index` (`is_active`,`retention_type`),
  KEY `data_retention_policies_created_by_index` (`created_by`),
  KEY `data_retention_policies_updated_by_index` (`updated_by`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `data_retention_policies`
--

LOCK TABLES `data_retention_policies` WRITE;
/*!40000 ALTER TABLE `data_retention_policies` DISABLE KEYS */;
INSERT INTO `data_retention_policies` VALUES (1,'audit_logs','2 years','soft_delete',1,'Audit logs are soft deleted after 2 years','2025-10-15 09:17:18','2025-10-15 09:17:18',NULL,NULL),(2,'project_activities','1 year','soft_delete',1,'Project activities are soft deleted after 1 year','2025-10-15 09:17:18','2025-10-15 09:17:18',NULL,NULL),(3,'query_logs','30 days','hard_delete',1,'Query logs are permanently deleted after 30 days','2025-10-15 09:17:18','2025-10-15 09:17:18',NULL,NULL),(4,'notifications','90 days','soft_delete',1,'Notifications are soft deleted after 90 days','2025-10-15 09:17:18','2025-10-15 09:17:18',NULL,NULL);
/*!40000 ALTER TABLE `data_retention_policies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_versions`
--

DROP TABLE IF EXISTS `document_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_versions` (
  `id` char(26) NOT NULL,
  `document_id` char(26) NOT NULL,
  `version_number` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `storage_driver` varchar(255) NOT NULL DEFAULT 'local',
  `comment` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_by` char(26) NOT NULL,
  `reverted_from_version_number` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `document_versions_document_id_version_number_unique` (`document_id`,`version_number`),
  KEY `document_versions_document_id_version_number_index` (`document_id`,`version_number`),
  KEY `document_versions_document_id_created_at_index` (`document_id`,`created_at`),
  KEY `document_versions_storage_driver_index` (`storage_driver`),
  KEY `document_versions_created_by_index` (`created_by`),
  KEY `document_versions_document_created_index` (`document_id`,`created_at`),
  KEY `document_versions_created_by_created_index` (`created_by`,`created_at`),
  KEY `idx_document_versions_doc_version` (`document_id`,`version_number`),
  KEY `idx_document_versions_creator_created` (`created_by`,`created_at`),
  KEY `document_versions_updated_by_index` (`updated_by`),
  CONSTRAINT `document_versions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `document_versions_document_id_foreign` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_versions`
--

LOCK TABLES `document_versions` WRITE;
/*!40000 ALTER TABLE `document_versions` DISABLE KEYS */;
/*!40000 ALTER TABLE `document_versions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `documents` (
  `id` char(26) NOT NULL,
  `deprecated_notice` varchar(255) DEFAULT NULL,
  `project_id` char(26) DEFAULT NULL,
  `tenant_id` char(26) DEFAULT NULL,
  `uploaded_by` char(26) NOT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(255) NOT NULL,
  `mime_type` varchar(255) NOT NULL,
  `file_size` bigint(20) NOT NULL,
  `file_hash` varchar(255) NOT NULL,
  `category` varchar(255) NOT NULL DEFAULT 'general',
  `description` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `version` int(11) NOT NULL DEFAULT 1,
  `is_current_version` tinyint(1) NOT NULL DEFAULT 1,
  `parent_document_id` char(26) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `documents_file_hash_unique` (`file_hash`),
  KEY `zena_documents_project_id_category_index` (`project_id`,`category`),
  KEY `zena_documents_file_hash_index` (`file_hash`),
  KEY `zena_documents_parent_document_id_version_index` (`parent_document_id`,`version`),
  KEY `documents_project_status_index` (`project_id`,`status`),
  KEY `documents_uploader_status_index` (`uploaded_by`,`status`),
  KEY `documents_type_status_index` (`file_type`,`status`),
  KEY `documents_hash_status_index` (`file_hash`,`status`),
  KEY `documents_tenant_status_index` (`tenant_id`,`status`),
  KEY `documents_project_category_status_index` (`project_id`,`category`,`status`),
  KEY `documents_created_at_index` (`created_at`),
  KEY `idx_documents_uploader_created` (`uploaded_by`,`created_at`),
  KEY `idx_documents_project_created` (`project_id`,`created_at`),
  KEY `idx_documents_category_created` (`category`,`created_at`),
  KEY `idx_documents_status_created` (`status`,`created_at`),
  KEY `documents_created_by_index` (`created_by`),
  KEY `documents_updated_by_index` (`updated_by`),
  CONSTRAINT `documents_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `documents_parent_document_id_foreign` FOREIGN KEY (`parent_document_id`) REFERENCES `documents` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `documents_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `documents_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `documents_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `documents_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents`
--

LOCK TABLES `documents` WRITE;
/*!40000 ALTER TABLE `documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_tracking`
--

DROP TABLE IF EXISTS `email_tracking`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_tracking` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tracking_id` varchar(255) NOT NULL,
  `email_type` varchar(255) NOT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `recipient_name` varchar(255) DEFAULT NULL,
  `invitation_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `organization_id` bigint(20) unsigned DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `content_hash` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `status` enum('pending','sent','delivered','opened','clicked','bounced','failed') NOT NULL DEFAULT 'pending',
  `sent_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `opened_at` timestamp NULL DEFAULT NULL,
  `clicked_at` timestamp NULL DEFAULT NULL,
  `bounced_at` timestamp NULL DEFAULT NULL,
  `failed_at` timestamp NULL DEFAULT NULL,
  `open_count` int(11) NOT NULL DEFAULT 0,
  `click_count` int(11) NOT NULL DEFAULT 0,
  `open_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`open_details`)),
  `click_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`click_details`)),
  `error_message` text DEFAULT NULL,
  `provider_response` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email_tracking_tracking_id_unique` (`tracking_id`),
  KEY `email_tracking_email_type_status_index` (`email_type`,`status`),
  KEY `email_tracking_recipient_email_created_at_index` (`recipient_email`,`created_at`),
  KEY `email_tracking_invitation_id_index` (`invitation_id`),
  KEY `email_tracking_user_id_index` (`user_id`),
  KEY `email_tracking_organization_id_index` (`organization_id`),
  KEY `email_tracking_tracking_id_index` (`tracking_id`),
  KEY `email_tracking_created_by_index` (`created_by`),
  KEY `email_tracking_updated_by_index` (`updated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_tracking`
--

LOCK TABLES `email_tracking` WRITE;
/*!40000 ALTER TABLE `email_tracking` DISABLE KEYS */;
/*!40000 ALTER TABLE `email_tracking` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invitations`
--

DROP TABLE IF EXISTS `invitations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invitations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `token` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `role` varchar(255) NOT NULL DEFAULT 'user',
  `message` text DEFAULT NULL,
  `organization_id` bigint(20) unsigned NOT NULL,
  `project_id` bigint(20) unsigned DEFAULT NULL,
  `invited_by` bigint(20) unsigned NOT NULL,
  `status` enum('pending','accepted','expired','cancelled') NOT NULL DEFAULT 'pending',
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `accepted_at` timestamp NULL DEFAULT NULL,
  `accepted_by` bigint(20) unsigned DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invitations_token_unique` (`token`),
  KEY `invitations_email_status_index` (`email`,`status`),
  KEY `invitations_organization_id_status_index` (`organization_id`,`status`),
  KEY `invitations_token_status_index` (`token`,`status`),
  KEY `invitations_expires_at_index` (`expires_at`),
  KEY `invitations_created_by_index` (`created_by`),
  KEY `invitations_updated_by_index` (`updated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invitations`
--

LOCK TABLES `invitations` WRITE;
/*!40000 ALTER TABLE `invitations` DISABLE KEYS */;
/*!40000 ALTER TABLE `invitations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`),
  KEY `jobs_created_by_index` (`created_by`),
  KEY `jobs_updated_by_index` (`updated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts` (
  `id` char(26) NOT NULL,
  `user_id` varchar(255) DEFAULT NULL,
  `tenant_id` varchar(255) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `ip_address` varchar(255) NOT NULL,
  `country` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `region` varchar(255) DEFAULT NULL,
  `isp` varchar(255) DEFAULT NULL,
  `user_agent` varchar(255) NOT NULL,
  `browser` varchar(255) DEFAULT NULL,
  `os` varchar(255) DEFAULT NULL,
  `device_type` varchar(255) DEFAULT NULL,
  `is_suspicious` tinyint(1) NOT NULL DEFAULT 0,
  `risk_score` double(8,2) NOT NULL DEFAULT 0.00,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `login_attempts_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `login_attempts_tenant_id_created_at_index` (`tenant_id`,`created_at`),
  KEY `login_attempts_status_created_at_index` (`status`,`created_at`),
  KEY `login_attempts_ip_address_created_at_index` (`ip_address`,`created_at`),
  KEY `login_attempts_email_created_at_index` (`email`,`created_at`),
  KEY `login_attempts_is_suspicious_index` (`is_suspicious`),
  KEY `login_attempts_created_by_index` (`created_by`),
  KEY `login_attempts_updated_by_index` (`updated_by`),
  CONSTRAINT `login_attempts_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `login_attempts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_attempts`
--

LOCK TABLES `login_attempts` WRITE;
/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
/*!40000 ALTER TABLE `login_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `maintenance_tasks`
--

DROP TABLE IF EXISTS `maintenance_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `maintenance_tasks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `task` varchar(255) NOT NULL,
  `level` varchar(255) NOT NULL DEFAULT 'info',
  `priority` varchar(255) NOT NULL DEFAULT 'medium',
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `user_id` char(26) DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `maintenance_tasks_user_id_foreign` (`user_id`),
  KEY `maintenance_tasks_status_priority_index` (`status`,`priority`),
  CONSTRAINT `maintenance_tasks_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `maintenance_tasks`
--

LOCK TABLES `maintenance_tasks` WRITE;
/*!40000 ALTER TABLE `maintenance_tasks` DISABLE KEYS */;
/*!40000 ALTER TABLE `maintenance_tasks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=145 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'2019_12_14_000001_create_personal_access_tokens_table',1),(2,'2024_09_27_000000_create_tenant_audit_logs_table',1),(3,'2024_09_27_000001_add_tenants_performance_indexes',1),(4,'2025_01_15_000001_create_clients_table',1),(5,'2025_01_15_000002_create_quotes_table',1),(6,'2025_09_14_100000_create_zena_users_table',1),(7,'2025_09_14_110000_create_zena_system_tables',1),(8,'2025_09_14_140000_create_zena_rbac_fixed',1),(9,'2025_09_14_150000_modify_personal_access_tokens_for_ulid',1),(10,'2025_09_14_160240_create_tenants_table',1),(11,'2025_09_14_160300_create_zena_components_table',1),(12,'2025_09_14_160316_create_zena_task_assignments_table',1),(13,'2025_09_14_160324_create_zena_documents_table',1),(14,'2025_09_14_160353_add_tenant_id_to_users_table',1),(15,'2025_09_14_160418_add_tenant_id_to_zena_projects_table',1),(16,'2025_09_14_160450_add_parent_foreign_key_to_zena_components_table',1),(17,'2025_09_14_160538_add_parent_document_foreign_key_to_zena_documents_table',1),(18,'2025_09_14_160630_add_profile_data_to_users_table',1),(19,'2025_09_15_041906_create_projects_table',1),(20,'2025_09_15_042450_create_tasks_table',1),(21,'2025_09_15_094811_add_tenant_constraints_for_security',1),(22,'2025_09_15_095740_add_email_verification_fields_to_users_table',1),(23,'2025_09_15_100111_add_mfa_fields_to_users_table',1),(24,'2025_09_15_100604_create_user_sessions_table',1),(25,'2025_09_15_100956_add_password_policy_fields_to_users_table',1),(26,'2025_09_15_103700_add_sso_fields_to_users_table',1),(27,'2025_09_15_144442_unify_projects_table_schema',1),(28,'2025_09_15_150122_create_project_activities_table',1),(29,'2025_09_15_151311_create_calendar_integrations_table',1),(30,'2025_09_15_162558_create_project_team_members_table',1),(31,'2025_09_15_162930_create_project_milestones_table',1),(32,'2025_09_15_163403_add_missing_columns_to_project_milestones_table',1),(33,'2025_09_16_082322_create_task_watchers_table',1),(34,'2025_09_16_082344_create_task_dependencies_table',1),(35,'2025_09_16_082646_deprecate_zena_task_tables',1),(36,'2025_09_16_082723_create_task_assignments_table',1),(37,'2025_09_16_083456_deprecate_zena_design_construction_tables',1),(38,'2025_09_16_084517_create_teams_table',1),(39,'2025_09_16_084549_create_team_members_table',1),(40,'2025_09_16_084621_create_project_teams_table',1),(41,'2025_09_16_084654_add_team_support_to_task_assignments_table',1),(42,'2025_09_17_043044_add_missing_fields_to_tasks_table',1),(43,'2025_09_17_043146_add_tenant_id_to_task_assignments_table',1),(44,'2025_09_17_043316_add_tenant_id_to_zena_notifications_table',1),(45,'2025_09_17_043416_add_is_read_to_zena_notifications_table',1),(46,'2025_09_17_043659_add_tenant_id_to_task_dependencies_table',1),(47,'2025_09_17_044546_add_tenant_id_to_zena_documents_table',1),(48,'2025_09_17_044822_make_project_id_nullable_in_zena_documents_table',1),(49,'2025_09_17_162350_create_change_requests_table',1),(50,'2025_09_17_162400_create_change_request_comments_table',1),(51,'2025_09_17_162450_create_change_request_approvals_table',1),(52,'2025_09_17_162520_add_created_by_to_projects_table',1),(53,'2025_09_17_163151_add_role_to_users_table',1),(54,'2025_09_17_165315_add_tenant_id_to_zena_roles_table',1),(55,'2025_09_18_053122_create_organizations_table',1),(56,'2025_09_18_053143_create_invitations_table',1),(57,'2025_09_18_053202_add_organization_fields_to_users_table',1),(58,'2025_09_18_055536_create_email_tracking_table',1),(59,'2025_09_18_075143_create_jobs_table',1),(60,'2025_09_19_173121_create_components_table',1),(61,'2025_09_19_174648_rename_zena_tables_to_standard_names',1),(62,'2025_09_20_071043_add_missing_performance_indexes',1),(63,'2025_09_20_071616_optimize_existing_tables_structure',1),(64,'2025_09_20_071704_create_query_logs_table',1),(65,'2025_09_20_074252_create_cache_entries_table',1),(66,'2025_09_20_132400_add_missing_fields_to_components_table',1),(67,'2025_09_20_133629_create_rfis_table',1),(68,'2025_09_20_141042_create_document_versions_table',1),(69,'2025_09_20_141420_fix_documents_foreign_key_constraints',1),(70,'2025_09_20_141930_create_qc_plans_table',1),(71,'2025_09_20_142005_create_qc_inspections_table',1),(72,'2025_09_20_142033_create_ncrs_table',1),(73,'2025_09_20_145540_fix_documents_table_completely',1),(74,'2025_09_20_145756_disable_foreign_keys_for_testing',1),(75,'2025_09_20_150000_create_dashboard_widgets_table',1),(76,'2025_09_20_150100_create_user_dashboards_table',1),(77,'2025_09_20_150200_create_dashboard_widget_data_cache_table',1),(78,'2025_09_20_160000_fix_notifications_table_schema',1),(79,'2025_09_20_160100_recreate_notifications_table',1),(80,'2025_09_20_163612_fix_project_milestones_table_schema',1),(81,'2025_09_20_164912_add_missing_columns_to_task_assignments_table',1),(82,'2025_09_22_012416_optimize_documents_table_schema',1),(83,'2025_09_22_012440_optimize_document_versions_table_schema',1),(84,'2025_09_22_012453_optimize_project_activities_table_schema',1),(85,'2025_09_22_012507_optimize_audit_logs_table_schema',1),(86,'2025_09_22_012614_add_data_retention_policies',1),(87,'2025_09_22_013614_add_missing_indexes_for_n1_optimization',1),(88,'2025_09_26_130043_create_search_histories_table',1),(89,'2025_09_26_152547_create_report_schedules_table',1),(90,'2025_09_26_162509_create_onboarding_steps_table',1),(91,'2025_09_26_165500_add_performance_indexes_phase3',1),(92,'2025_09_27_064556_create_backup_logs_table',1),(93,'2025_09_28_151538_add_indexes_security',1),(94,'2025_09_30_165820_create_login_attempts_table',1),(95,'2025_09_30_165845_create_security_alerts_table',1),(96,'2025_09_30_165901_create_security_rules_table',1),(97,'2025_10_01_074837_create_system_settings_table',1),(98,'2025_10_01_095353_create_billing_plans_table',1),(99,'2025_10_01_095416_create_tenant_subscriptions_table',1),(100,'2025_10_01_095432_create_billing_invoices_table',1),(101,'2025_10_01_095444_create_billing_payments_table',1),(102,'2025_10_01_151124_create_system_alerts_table',1),(103,'2025_10_04_003555_create_audit_logs_table',1),(104,'2025_10_06_000001_add_indexes',1),(105,'2025_10_06_001537_enable_foreign_key_constraints',1),(106,'2025_10_06_003128_create_user_preferences_table',1),(107,'2025_10_06_011523_add_preferences_to_tenants_table',1),(108,'2025_10_06_142650_create_templates_table',1),(109,'2025_10_07_021725_add_created_by_updated_by_to_documents_table',1),(110,'2025_10_07_021819_add_missing_created_by_updated_by_columns_to_all_tables',1),(111,'2025_10_07_021926_fix_created_by_updated_by_data_types',1),(112,'2025_10_07_025428_add_updated_by_to_templates_table',1),(113,'2025_10_07_025628_disable_telescope_for_testing',1),(114,'2025_10_07_082703_add_status_to_templates_table',1),(115,'2025_10_07_082841_add_missing_fields_to_templates_table',1),(116,'2025_10_07_154008_add_plan_to_tenants_table',1),(117,'2025_10_08_073151_create_sessions_table',1),(118,'2025_10_11_044841_add_file_type_to_documents_table',1),(119,'2025_10_14_042005_create_zena_permissions_table',1),(120,'2025_10_14_042107_create_calendar_events_table',1),(121,'2025_10_14_042204_add_missing_columns_to_users_table',1),(122,'2025_10_14_042306_add_missing_columns_to_projects_table',1),(123,'2025_10_14_042401_add_missing_columns_to_documents_table',1),(124,'2025_10_14_042456_fix_notifications_table_add_id_column',1),(125,'2025_10_14_044111_create_dashboards_table',1),(126,'2025_10_14_045350_create_widgets_table',1),(127,'2025_10_14_081501_create_maintenance_tasks_table',1),(128,'2025_10_14_082346_create_performance_metrics_table',1),(129,'2025_10_14_083908_create_support_tickets_table',1),(130,'2025_10_14_084014_create_support_messages_table',1),(131,'2025_10_14_090518_fix_tenant_id_default_value_in_users_table',1),(132,'2025_10_14_090752_fix_widgets_tenant_id_default',1),(133,'2025_10_14_091201_recreate_widgets_tenant_id_column',1),(134,'2025_10_14_092856_debug_widgets_schema',1),(135,'2025_10_14_103802_create_dashboard_metrics_table',1),(136,'2025_10_14_104937_create_zena_roles_table',1),(137,'2025_10_14_105124_create_zena_role_permissions_table',1),(138,'2025_10_14_114500_create_dashboard_metric_values_table',1),(139,'2025_10_14_114603_create_user_roles_table',1),(140,'2025_10_14_121723_fix_user_roles_constraint',1),(141,'2025_10_14_164752_create_dashboard_alerts_table',1),(142,'2025_10_14_233506_create_user_dashboards_table',1),(143,'2025_10_14_233629_create_dashboard_widgets_table',1),(144,'2025_10_15_002421_add_current_project_id_to_users_table',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ncrs`
--

DROP TABLE IF EXISTS `ncrs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ncrs` (
  `id` char(26) NOT NULL,
  `project_id` char(26) NOT NULL,
  `tenant_id` char(26) NOT NULL,
  `inspection_id` char(26) DEFAULT NULL,
  `ncr_number` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `status` enum('open','under_review','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
  `severity` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `created_by` char(26) NOT NULL,
  `assigned_to` char(26) DEFAULT NULL,
  `root_cause` text DEFAULT NULL,
  `corrective_action` text DEFAULT NULL,
  `preventive_action` text DEFAULT NULL,
  `resolution` text DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `attachments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attachments`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ncrs_ncr_number_unique` (`ncr_number`),
  KEY `ncrs_project_id_status_index` (`project_id`,`status`),
  KEY `ncrs_tenant_id_index` (`tenant_id`),
  KEY `ncrs_inspection_id_index` (`inspection_id`),
  KEY `ncrs_created_by_index` (`created_by`),
  KEY `ncrs_assigned_to_index` (`assigned_to`),
  KEY `ncrs_severity_index` (`severity`),
  KEY `ncrs_ncr_number_index` (`ncr_number`),
  KEY `ncrs_updated_by_index` (`updated_by`),
  CONSTRAINT `ncrs_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ncrs_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ncrs_inspection_id_foreign` FOREIGN KEY (`inspection_id`) REFERENCES `qc_inspections` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ncrs_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ncrs_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ncrs`
--

LOCK TABLES `ncrs` WRITE;
/*!40000 ALTER TABLE `ncrs` DISABLE KEYS */;
/*!40000 ALTER TABLE `ncrs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` char(26) NOT NULL,
  `user_id` char(26) NOT NULL,
  `tenant_id` char(26) NOT NULL,
  `type` varchar(255) NOT NULL,
  `priority` enum('critical','normal','low') NOT NULL DEFAULT 'normal',
  `title` varchar(255) NOT NULL,
  `body` text DEFAULT NULL,
  `link_url` varchar(255) DEFAULT NULL,
  `channel` enum('inapp','email','webhook') NOT NULL DEFAULT 'inapp',
  `read_at` timestamp NULL DEFAULT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `event_key` varchar(255) DEFAULT NULL,
  `project_id` char(26) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_user_id_read_at_index` (`user_id`,`read_at`),
  KEY `notifications_tenant_id_index` (`tenant_id`),
  KEY `notifications_priority_index` (`priority`),
  KEY `notifications_channel_index` (`channel`),
  KEY `notifications_project_id_index` (`project_id`),
  KEY `notifications_event_key_index` (`event_key`),
  KEY `notifications_type_index` (`type`),
  KEY `idx_notifications_tenant_user_read` (`tenant_id`,`user_id`,`read_at`),
  KEY `idx_notifications_user_created` (`user_id`,`created_at`),
  KEY `idx_notifications_type_created` (`type`,`created_at`),
  KEY `notifications_created_by_index` (`created_by`),
  KEY `notifications_updated_by_index` (`updated_by`),
  CONSTRAINT `notifications_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notifications_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `onboarding_steps`
--

DROP TABLE IF EXISTS `onboarding_steps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `onboarding_steps` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `type` varchar(255) NOT NULL,
  `target_element` varchar(255) DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`content`)),
  `actions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`actions`)),
  `order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `role` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `onboarding_steps_key_unique` (`key`),
  KEY `onboarding_steps_is_active_order_index` (`is_active`,`order`),
  KEY `onboarding_steps_type_is_active_index` (`type`,`is_active`),
  KEY `onboarding_steps_created_by_index` (`created_by`),
  KEY `onboarding_steps_updated_by_index` (`updated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `onboarding_steps`
--

LOCK TABLES `onboarding_steps` WRITE;
/*!40000 ALTER TABLE `onboarding_steps` DISABLE KEYS */;
/*!40000 ALTER TABLE `onboarding_steps` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `organizations`
--

DROP TABLE IF EXISTS `organizations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `organizations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `domain` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `logo_url` varchar(255) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `timezone` varchar(255) NOT NULL DEFAULT 'UTC',
  `currency` varchar(255) NOT NULL DEFAULT 'USD',
  `language` varchar(255) NOT NULL DEFAULT 'en',
  `allow_self_registration` tinyint(1) NOT NULL DEFAULT 0,
  `require_email_verification` tinyint(1) NOT NULL DEFAULT 1,
  `require_admin_approval` tinyint(1) NOT NULL DEFAULT 0,
  `allowed_domains` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`allowed_domains`)),
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`settings`)),
  `status` enum('active','suspended','pending') NOT NULL DEFAULT 'active',
  `trial_ends_at` timestamp NULL DEFAULT NULL,
  `subscription_ends_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `organizations_slug_unique` (`slug`),
  KEY `organizations_status_created_at_index` (`status`,`created_at`),
  KEY `organizations_domain_index` (`domain`),
  KEY `organizations_created_by_index` (`created_by`),
  KEY `organizations_updated_by_index` (`updated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `organizations`
--

LOCK TABLES `organizations` WRITE;
/*!40000 ALTER TABLE `organizations` DISABLE KEYS */;
/*!40000 ALTER TABLE `organizations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `performance_metrics`
--

DROP TABLE IF EXISTS `performance_metrics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `performance_metrics` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `metric_name` varchar(255) NOT NULL,
  `metric_value` decimal(10,2) NOT NULL,
  `metric_unit` varchar(255) NOT NULL,
  `category` varchar(255) NOT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `performance_metrics_metric_name_category_index` (`metric_name`,`category`),
  KEY `performance_metrics_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `performance_metrics`
--

LOCK TABLES `performance_metrics` WRITE;
/*!40000 ALTER TABLE `performance_metrics` DISABLE KEYS */;
/*!40000 ALTER TABLE `performance_metrics` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` char(26) NOT NULL,
  `code` varchar(255) NOT NULL,
  `module` varchar(255) NOT NULL,
  `action` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `zena_permissions_code_unique` (`code`),
  KEY `permissions_created_by_index` (`created_by`),
  KEY `permissions_updated_by_index` (`updated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` varchar(26) NOT NULL,
  `name` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_access_tokens`
--

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_activities`
--

DROP TABLE IF EXISTS `project_activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_activities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` varchar(255) NOT NULL,
  `tenant_id` varchar(255) DEFAULT NULL,
  `user_id` varchar(255) NOT NULL,
  `action` varchar(255) NOT NULL,
  `entity_type` varchar(255) NOT NULL,
  `entity_id` varchar(255) DEFAULT NULL,
  `description` text NOT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `ip_address` varchar(255) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `project_activities_project_id_created_at_index` (`project_id`,`created_at`),
  KEY `project_activities_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `project_activities_action_entity_type_index` (`action`,`entity_type`),
  KEY `project_activities_entity_history_index` (`entity_type`,`entity_id`,`created_at`),
  KEY `project_activities_action_created_index` (`action`,`created_at`),
  KEY `project_activities_tenant_created_index` (`tenant_id`,`created_at`),
  KEY `idx_project_activities_action_created` (`action`,`created_at`),
  KEY `idx_project_activities_entity_type_created` (`entity_type`,`created_at`),
  KEY `project_activities_created_by_index` (`created_by`),
  KEY `project_activities_updated_by_index` (`updated_by`),
  CONSTRAINT `project_activities_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_activities_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_activities_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_activities`
--

LOCK TABLES `project_activities` WRITE;
/*!40000 ALTER TABLE `project_activities` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_activities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_milestones`
--

DROP TABLE IF EXISTS `project_milestones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_milestones` (
  `id` char(26) NOT NULL,
  `project_id` char(26) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `target_date` date DEFAULT NULL,
  `completed_date` date DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `order` int(11) NOT NULL DEFAULT 0,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_by` char(26) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `project_milestones_created_by_foreign` (`created_by`),
  KEY `project_milestones_project_id_status_index` (`project_id`,`status`),
  KEY `project_milestones_project_id_order_index` (`project_id`,`order`),
  KEY `project_milestones_target_date_index` (`target_date`),
  KEY `project_milestones_status_index` (`status`),
  KEY `project_milestones_updated_by_index` (`updated_by`),
  CONSTRAINT `project_milestones_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `project_milestones_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_milestones`
--

LOCK TABLES `project_milestones` WRITE;
/*!40000 ALTER TABLE `project_milestones` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_milestones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_team_members`
--

DROP TABLE IF EXISTS `project_team_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_team_members` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` varchar(255) NOT NULL,
  `user_id` varchar(255) NOT NULL,
  `role` varchar(255) NOT NULL DEFAULT 'member',
  `joined_at` timestamp NULL DEFAULT NULL,
  `left_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `project_team_members_project_id_user_id_unique` (`project_id`,`user_id`),
  KEY `project_team_members_project_id_role_index` (`project_id`,`role`),
  KEY `project_team_members_project_user_index` (`project_id`,`user_id`),
  KEY `project_team_members_user_created_index` (`user_id`,`created_at`),
  KEY `project_team_members_created_by_index` (`created_by`),
  KEY `project_team_members_updated_by_index` (`updated_by`),
  CONSTRAINT `project_team_members_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_team_members_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_team_members`
--

LOCK TABLES `project_team_members` WRITE;
/*!40000 ALTER TABLE `project_team_members` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_team_members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_teams`
--

DROP TABLE IF EXISTS `project_teams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_teams` (
  `project_id` char(26) NOT NULL,
  `team_id` char(26) NOT NULL,
  `role` varchar(255) NOT NULL DEFAULT 'contributor',
  `joined_at` timestamp NULL DEFAULT NULL,
  `left_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`project_id`,`team_id`),
  KEY `project_teams_project_id_role_index` (`project_id`,`role`),
  KEY `project_teams_team_id_project_id_index` (`team_id`,`project_id`),
  KEY `project_teams_joined_at_index` (`joined_at`),
  KEY `project_teams_left_at_index` (`left_at`),
  KEY `project_teams_created_by_index` (`created_by`),
  KEY `project_teams_updated_by_index` (`updated_by`),
  CONSTRAINT `project_teams_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_teams_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_teams`
--

LOCK TABLES `project_teams` WRITE;
/*!40000 ALTER TABLE `project_teams` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_teams` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `projects`
--

DROP TABLE IF EXISTS `projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `projects` (
  `id` char(26) NOT NULL,
  `tenant_id` varchar(255) DEFAULT NULL,
  `code` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `client_id` varchar(255) DEFAULT NULL,
  `pm_id` varchar(255) DEFAULT NULL,
  `created_by` varchar(255) DEFAULT NULL,
  `owner_id` varchar(255) DEFAULT NULL,
  `user_id` char(26) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `priority` enum('low','normal','medium','high','urgent') NOT NULL DEFAULT 'normal',
  `is_template` tinyint(1) NOT NULL DEFAULT 0,
  `template_id` varchar(255) DEFAULT NULL,
  `last_activity_at` timestamp NULL DEFAULT NULL,
  `completion_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `progress` decimal(5,2) NOT NULL DEFAULT 0.00,
  `progress_pct` int(11) NOT NULL DEFAULT 0,
  `budget_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `estimated_hours` decimal(10,2) NOT NULL DEFAULT 0.00,
  `actual_hours` decimal(10,2) NOT NULL DEFAULT 0.00,
  `risk_level` enum('low','medium','high','critical') NOT NULL DEFAULT 'low',
  `budget_planned` decimal(15,2) NOT NULL DEFAULT 0.00,
  `budget_actual` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`settings`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `projects_code_unique` (`code`),
  KEY `projects_tenant_id_index` (`tenant_id`),
  KEY `projects_status_index` (`status`),
  KEY `projects_client_id_index` (`client_id`),
  KEY `projects_pm_id_index` (`pm_id`),
  KEY `idx_projects_tenant_id` (`tenant_id`,`id`),
  KEY `projects_tenant_id_status_index` (`tenant_id`,`status`),
  KEY `projects_client_id_status_index` (`client_id`,`status`),
  KEY `projects_pm_id_status_index` (`pm_id`,`status`),
  KEY `projects_date_range_index` (`start_date`,`end_date`),
  KEY `projects_progress_status_index` (`progress`,`status`),
  KEY `projects_created_at_index` (`created_at`),
  KEY `projects_tenant_status_index` (`tenant_id`,`status`),
  KEY `idx_projects_tenant_budget` (`tenant_id`,`budget_total`),
  KEY `idx_projects_status_created` (`status`,`created_at`),
  KEY `idx_projects_creator_created` (`created_by`,`created_at`),
  KEY `projects_updated_by_index` (`updated_by`),
  KEY `projects_user_id_foreign` (`user_id`),
  CONSTRAINT `projects_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `projects_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `projects_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `projects`
--

LOCK TABLES `projects` WRITE;
/*!40000 ALTER TABLE `projects` DISABLE KEYS */;
/*!40000 ALTER TABLE `projects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qc_inspections`
--

DROP TABLE IF EXISTS `qc_inspections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qc_inspections` (
  `id` char(26) NOT NULL,
  `qc_plan_id` char(26) NOT NULL,
  `tenant_id` char(26) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('scheduled','in_progress','completed','failed') NOT NULL DEFAULT 'scheduled',
  `inspection_date` date NOT NULL,
  `inspector_id` char(26) NOT NULL,
  `findings` text DEFAULT NULL,
  `recommendations` text DEFAULT NULL,
  `checklist_results` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`checklist_results`)),
  `photos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`photos`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `qc_inspections_qc_plan_id_status_index` (`qc_plan_id`,`status`),
  KEY `qc_inspections_tenant_id_index` (`tenant_id`),
  KEY `qc_inspections_inspector_id_index` (`inspector_id`),
  KEY `qc_inspections_inspection_date_index` (`inspection_date`),
  KEY `qc_inspections_created_by_index` (`created_by`),
  KEY `qc_inspections_updated_by_index` (`updated_by`),
  CONSTRAINT `qc_inspections_inspector_id_foreign` FOREIGN KEY (`inspector_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `qc_inspections_qc_plan_id_foreign` FOREIGN KEY (`qc_plan_id`) REFERENCES `qc_plans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `qc_inspections_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qc_inspections`
--

LOCK TABLES `qc_inspections` WRITE;
/*!40000 ALTER TABLE `qc_inspections` DISABLE KEYS */;
/*!40000 ALTER TABLE `qc_inspections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qc_plans`
--

DROP TABLE IF EXISTS `qc_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qc_plans` (
  `id` char(26) NOT NULL,
  `project_id` char(26) NOT NULL,
  `tenant_id` char(26) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('draft','active','completed','cancelled') NOT NULL DEFAULT 'draft',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_by` char(26) NOT NULL,
  `checklist_items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`checklist_items`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `qc_plans_project_id_status_index` (`project_id`,`status`),
  KEY `qc_plans_tenant_id_index` (`tenant_id`),
  KEY `qc_plans_created_by_index` (`created_by`),
  KEY `qc_plans_updated_by_index` (`updated_by`),
  CONSTRAINT `qc_plans_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `qc_plans_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `qc_plans_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qc_plans`
--

LOCK TABLES `qc_plans` WRITE;
/*!40000 ALTER TABLE `qc_plans` DISABLE KEYS */;
/*!40000 ALTER TABLE `qc_plans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `query_logs`
--

DROP TABLE IF EXISTS `query_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `query_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `query_hash` varchar(64) NOT NULL,
  `sql` text NOT NULL,
  `bindings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`bindings`)),
  `execution_time` decimal(8,3) NOT NULL,
  `connection` varchar(50) NOT NULL DEFAULT 'mysql',
  `user_id` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `method` varchar(10) DEFAULT NULL,
  `memory_usage` int(11) DEFAULT NULL,
  `rows_affected` int(11) DEFAULT NULL,
  `rows_returned` int(11) DEFAULT NULL,
  `query_type` enum('SELECT','INSERT','UPDATE','DELETE','OTHER') NOT NULL DEFAULT 'OTHER',
  `is_slow` tinyint(1) NOT NULL DEFAULT 0,
  `is_error` tinyint(1) NOT NULL DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `executed_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `query_logs_executed_at_is_slow_index` (`executed_at`,`is_slow`),
  KEY `query_logs_user_id_executed_at_index` (`user_id`,`executed_at`),
  KEY `query_logs_query_type_execution_time_index` (`query_type`,`execution_time`),
  KEY `query_logs_connection_execution_time_index` (`connection`,`execution_time`),
  KEY `query_logs_is_error_executed_at_index` (`is_error`,`executed_at`),
  KEY `query_logs_query_hash_index` (`query_hash`),
  KEY `query_logs_created_by_index` (`created_by`),
  KEY `query_logs_updated_by_index` (`updated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `query_logs`
--

LOCK TABLES `query_logs` WRITE;
/*!40000 ALTER TABLE `query_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `query_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quotes`
--

DROP TABLE IF EXISTS `quotes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quotes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` char(26) NOT NULL,
  `client_id` char(26) NOT NULL,
  `project_id` bigint(20) unsigned DEFAULT NULL,
  `type` enum('design','construction') NOT NULL DEFAULT 'design',
  `status` enum('draft','sent','viewed','accepted','rejected','expired') NOT NULL DEFAULT 'draft',
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `total_amount` decimal(15,2) NOT NULL,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `final_amount` decimal(15,2) NOT NULL,
  `line_items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`line_items`)),
  `terms_conditions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`terms_conditions`)),
  `valid_until` date NOT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `viewed_at` timestamp NULL DEFAULT NULL,
  `accepted_at` timestamp NULL DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_by` char(26) NOT NULL,
  `updated_by` char(26) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `quotes_tenant_id_client_id_index` (`tenant_id`,`client_id`),
  KEY `quotes_tenant_id_status_index` (`tenant_id`,`status`),
  KEY `quotes_tenant_id_type_index` (`tenant_id`,`type`),
  KEY `quotes_tenant_id_valid_until_index` (`tenant_id`,`valid_until`),
  KEY `quotes_tenant_id_created_at_index` (`tenant_id`,`created_at`),
  KEY `quotes_tenant_id_index` (`tenant_id`),
  KEY `quotes_created_by_index` (`created_by`),
  KEY `quotes_updated_by_index` (`updated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quotes`
--

LOCK TABLES `quotes` WRITE;
/*!40000 ALTER TABLE `quotes` DISABLE KEYS */;
/*!40000 ALTER TABLE `quotes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `report_schedules`
--

DROP TABLE IF EXISTS `report_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `report_schedules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` varchar(255) NOT NULL,
  `tenant_id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `type` varchar(255) NOT NULL,
  `format` varchar(255) NOT NULL,
  `frequency` varchar(255) NOT NULL,
  `recipients` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`recipients`)),
  `filters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`filters`)),
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_sent_at` timestamp NULL DEFAULT NULL,
  `next_send_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `report_schedules_user_id_is_active_index` (`user_id`,`is_active`),
  KEY `report_schedules_tenant_id_next_send_at_index` (`tenant_id`,`next_send_at`),
  KEY `report_schedules_created_by_index` (`created_by`),
  KEY `report_schedules_updated_by_index` (`updated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `report_schedules`
--

LOCK TABLES `report_schedules` WRITE;
/*!40000 ALTER TABLE `report_schedules` DISABLE KEYS */;
/*!40000 ALTER TABLE `report_schedules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rfis`
--

DROP TABLE IF EXISTS `rfis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rfis` (
  `id` varchar(255) NOT NULL,
  `tenant_id` varchar(255) NOT NULL,
  `project_id` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `question` text NOT NULL,
  `rfi_number` varchar(255) NOT NULL,
  `priority` enum('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `location` varchar(255) DEFAULT NULL,
  `drawing_reference` varchar(255) DEFAULT NULL,
  `asked_by` varchar(255) NOT NULL,
  `created_by` varchar(255) NOT NULL,
  `assigned_to` varchar(255) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('open','answered','closed') NOT NULL DEFAULT 'open',
  `answer` text DEFAULT NULL,
  `response` text DEFAULT NULL,
  `answered_by` varchar(255) DEFAULT NULL,
  `responded_by` varchar(255) DEFAULT NULL,
  `answered_at` timestamp NULL DEFAULT NULL,
  `responded_at` timestamp NULL DEFAULT NULL,
  `assigned_at` timestamp NULL DEFAULT NULL,
  `assignment_notes` text DEFAULT NULL,
  `escalated_to` varchar(255) DEFAULT NULL,
  `escalation_reason` text DEFAULT NULL,
  `escalated_by` varchar(255) DEFAULT NULL,
  `escalated_at` timestamp NULL DEFAULT NULL,
  `closed_by` varchar(255) DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `attachments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attachments`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rfis_rfi_number_unique` (`rfi_number`),
  KEY `rfis_tenant_id_index` (`tenant_id`),
  KEY `rfis_project_id_status_index` (`project_id`,`status`),
  KEY `rfis_assigned_to_status_index` (`assigned_to`,`status`),
  KEY `rfis_due_date_index` (`due_date`),
  KEY `rfis_priority_index` (`priority`),
  KEY `rfis_created_at_index` (`created_at`),
  KEY `rfis_asked_by_foreign` (`asked_by`),
  KEY `rfis_answered_by_foreign` (`answered_by`),
  KEY `rfis_responded_by_foreign` (`responded_by`),
  KEY `rfis_escalated_to_foreign` (`escalated_to`),
  KEY `rfis_escalated_by_foreign` (`escalated_by`),
  KEY `rfis_closed_by_foreign` (`closed_by`),
  KEY `rfis_created_by_index` (`created_by`),
  KEY `rfis_updated_by_index` (`updated_by`),
  CONSTRAINT `rfis_answered_by_foreign` FOREIGN KEY (`answered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rfis_asked_by_foreign` FOREIGN KEY (`asked_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rfis_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rfis_closed_by_foreign` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rfis_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rfis_escalated_by_foreign` FOREIGN KEY (`escalated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rfis_escalated_to_foreign` FOREIGN KEY (`escalated_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rfis_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rfis_responded_by_foreign` FOREIGN KEY (`responded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rfis_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rfis`
--

LOCK TABLES `rfis` WRITE;
/*!40000 ALTER TABLE `rfis` DISABLE KEYS */;
/*!40000 ALTER TABLE `rfis` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_permissions`
--

DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_permissions` (
  `role_id` char(26) NOT NULL,
  `permission_id` char(26) NOT NULL,
  `allow_override` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `zena_role_permissions_permission_id_foreign` (`permission_id`),
  KEY `role_permissions_created_by_index` (`created_by`),
  KEY `role_permissions_updated_by_index` (`updated_by`),
  CONSTRAINT `zena_role_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `zena_role_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_permissions`
--

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` char(26) NOT NULL,
  `name` varchar(255) NOT NULL,
  `scope` varchar(255) NOT NULL DEFAULT 'system',
  `allow_override` tinyint(1) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `tenant_id` char(26) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `zena_roles_name_unique` (`name`),
  KEY `zena_roles_tenant_id_scope_index` (`tenant_id`,`scope`),
  KEY `roles_created_by_index` (`created_by`),
  KEY `roles_updated_by_index` (`updated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `search_histories`
--

DROP TABLE IF EXISTS `search_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `search_histories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` varchar(255) NOT NULL,
  `tenant_id` varchar(255) NOT NULL,
  `query` varchar(255) NOT NULL,
  `context` varchar(255) NOT NULL DEFAULT 'all',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `search_histories_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `search_histories_tenant_id_query_index` (`tenant_id`,`query`),
  KEY `search_histories_created_by_index` (`created_by`),
  KEY `search_histories_updated_by_index` (`updated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `search_histories`
--

LOCK TABLES `search_histories` WRITE;
/*!40000 ALTER TABLE `search_histories` DISABLE KEYS */;
/*!40000 ALTER TABLE `search_histories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `security_alerts`
--

DROP TABLE IF EXISTS `security_alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `security_alerts` (
  `id` char(26) NOT NULL,
  `rule_id` varchar(255) DEFAULT NULL,
  `tenant_id` varchar(255) DEFAULT NULL,
  `user_id` varchar(255) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `severity` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL,
  `category` varchar(255) NOT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `triggered_by` varchar(255) DEFAULT NULL,
  `assigned_to` varchar(255) DEFAULT NULL,
  `acknowledged_at` timestamp NULL DEFAULT NULL,
  `acknowledged_by` varchar(255) DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolved_by` varchar(255) DEFAULT NULL,
  `resolution_note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `security_alerts_tenant_id_created_at_index` (`tenant_id`,`created_at`),
  KEY `security_alerts_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `security_alerts_status_severity_created_at_index` (`status`,`severity`,`created_at`),
  KEY `security_alerts_category_created_at_index` (`category`,`created_at`),
  KEY `security_alerts_rule_id_index` (`rule_id`),
  KEY `security_alerts_created_by_index` (`created_by`),
  KEY `security_alerts_updated_by_index` (`updated_by`),
  CONSTRAINT `security_alerts_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `security_alerts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `security_alerts`
--

LOCK TABLES `security_alerts` WRITE;
/*!40000 ALTER TABLE `security_alerts` DISABLE KEYS */;
/*!40000 ALTER TABLE `security_alerts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `security_rules`
--

DROP TABLE IF EXISTS `security_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `security_rules` (
  `id` char(26) NOT NULL,
  `tenant_id` varchar(255) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `severity` varchar(255) NOT NULL,
  `conditions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`conditions`)),
  `actions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`actions`)),
  `destinations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`destinations`)),
  `trigger_count` int(11) NOT NULL DEFAULT 0,
  `last_triggered_at` timestamp NULL DEFAULT NULL,
  `created_by` varchar(255) DEFAULT NULL,
  `updated_by` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `security_rules_tenant_id_is_enabled_index` (`tenant_id`,`is_enabled`),
  KEY `security_rules_category_is_enabled_index` (`category`,`is_enabled`),
  KEY `security_rules_type_is_enabled_index` (`type`,`is_enabled`),
  KEY `security_rules_created_by_index` (`created_by`),
  KEY `security_rules_updated_by_index` (`updated_by`),
  CONSTRAINT `security_rules_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `security_rules`
--

LOCK TABLES `security_rules` WRITE;
/*!40000 ALTER TABLE `security_rules` DISABLE KEYS */;
/*!40000 ALTER TABLE `security_rules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `support_messages`
--

DROP TABLE IF EXISTS `support_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `support_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `support_ticket_id` bigint(20) unsigned NOT NULL,
  `user_id` char(26) NOT NULL,
  `message` text NOT NULL,
  `is_internal` tinyint(1) NOT NULL DEFAULT 0,
  `attachments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attachments`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `support_messages_user_id_foreign` (`user_id`),
  KEY `support_messages_support_ticket_id_created_at_index` (`support_ticket_id`,`created_at`),
  CONSTRAINT `support_messages_support_ticket_id_foreign` FOREIGN KEY (`support_ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `support_messages_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `support_messages`
--

LOCK TABLES `support_messages` WRITE;
/*!40000 ALTER TABLE `support_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `support_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `support_tickets`
--

DROP TABLE IF EXISTS `support_tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `support_tickets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `subject` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `category` varchar(255) NOT NULL DEFAULT 'general',
  `priority` varchar(255) NOT NULL DEFAULT 'medium',
  `status` varchar(255) NOT NULL DEFAULT 'open',
  `user_id` char(26) NOT NULL,
  `tenant_id` char(26) NOT NULL,
  `assigned_to` char(26) DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `support_tickets_tenant_id_foreign` (`tenant_id`),
  KEY `support_tickets_assigned_to_foreign` (`assigned_to`),
  KEY `support_tickets_status_priority_index` (`status`,`priority`),
  KEY `support_tickets_user_id_created_at_index` (`user_id`,`created_at`),
  CONSTRAINT `support_tickets_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `support_tickets_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `support_tickets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `support_tickets`
--

LOCK TABLES `support_tickets` WRITE;
/*!40000 ALTER TABLE `support_tickets` DISABLE KEYS */;
/*!40000 ALTER TABLE `support_tickets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_alerts`
--

DROP TABLE IF EXISTS `system_alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_alerts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `type` enum('system','security','performance','maintenance','user') NOT NULL,
  `severity` enum('info','warning','critical') NOT NULL,
  `status` enum('active','resolved') NOT NULL DEFAULT 'active',
  `created_by` varchar(255) DEFAULT NULL,
  `resolved_by` varchar(255) DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `system_alerts_status_severity_index` (`status`,`severity`),
  KEY `system_alerts_type_created_at_index` (`type`,`created_at`),
  KEY `system_alerts_created_at_index` (`created_at`),
  KEY `system_alerts_created_by_index` (`created_by`),
  KEY `system_alerts_updated_by_index` (`updated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_alerts`
--

LOCK TABLES `system_alerts` WRITE;
/*!40000 ALTER TABLE `system_alerts` DISABLE KEYS */;
/*!40000 ALTER TABLE `system_alerts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) NOT NULL,
  `value` text NOT NULL,
  `updated_by_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `system_settings_key_unique` (`key`),
  KEY `system_settings_key_updated_at_index` (`key`,`updated_at`),
  KEY `system_settings_created_by_index` (`created_by`),
  KEY `system_settings_updated_by_index` (`updated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_settings`
--

LOCK TABLES `system_settings` WRITE;
/*!40000 ALTER TABLE `system_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `system_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `task_assignments`
--

DROP TABLE IF EXISTS `task_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_assignments` (
  `id` char(26) NOT NULL,
  `tenant_id` varchar(255) DEFAULT NULL,
  `task_id` varchar(255) NOT NULL,
  `user_id` varchar(255) NOT NULL,
  `team_id` char(26) DEFAULT NULL,
  `assignment_type` varchar(255) NOT NULL DEFAULT 'user',
  `priority` varchar(255) DEFAULT NULL,
  `estimated_hours` decimal(8,2) DEFAULT NULL,
  `assigned_by` varchar(255) DEFAULT NULL,
  `due_date` timestamp NULL DEFAULT NULL,
  `role` varchar(255) NOT NULL DEFAULT 'assignee',
  `assigned_hours` decimal(8,2) DEFAULT NULL,
  `actual_hours` decimal(8,2) NOT NULL DEFAULT 0.00,
  `status` varchar(255) NOT NULL DEFAULT 'assigned',
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_task_user_role` (`task_id`,`user_id`,`role`),
  KEY `task_assignments_task_id_user_id_index` (`task_id`,`user_id`),
  KEY `task_assignments_user_id_status_index` (`user_id`,`status`),
  KEY `task_assignments_task_id_status_index` (`task_id`,`status`),
  KEY `task_assignments_role_index` (`role`),
  KEY `task_assignments_status_index` (`status`),
  KEY `task_assignments_team_id_assignment_type_index` (`team_id`,`assignment_type`),
  KEY `task_assignments_assignment_type_index` (`assignment_type`),
  KEY `task_assignments_assigned_by_foreign` (`assigned_by`),
  KEY `task_assignments_tenant_id_status_index` (`tenant_id`,`status`),
  KEY `task_assignments_priority_index` (`priority`),
  KEY `task_assignments_due_date_index` (`due_date`),
  KEY `task_assignments_task_user_index` (`task_id`,`user_id`),
  KEY `task_assignments_user_status_index` (`user_id`,`status`),
  KEY `task_assignments_tenant_status_index` (`tenant_id`,`status`),
  KEY `task_assignments_team_id_index` (`team_id`),
  KEY `task_assignments_created_by_index` (`created_by`),
  KEY `task_assignments_updated_by_index` (`updated_by`),
  CONSTRAINT `task_assignments_assigned_by_foreign` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `task_assignments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `task_assignments_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_assignments_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_assignments_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_assignments_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `task_assignments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `task_assignments`
--

LOCK TABLES `task_assignments` WRITE;
/*!40000 ALTER TABLE `task_assignments` DISABLE KEYS */;
/*!40000 ALTER TABLE `task_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `task_dependencies`
--

DROP TABLE IF EXISTS `task_dependencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_dependencies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` varchar(255) DEFAULT NULL,
  `task_id` varchar(255) NOT NULL,
  `dependency_id` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_task_dependency` (`task_id`,`dependency_id`),
  KEY `task_dependencies_task_id_dependency_id_index` (`task_id`,`dependency_id`),
  KEY `task_dependencies_dependency_id_task_id_index` (`dependency_id`,`task_id`),
  KEY `task_dependencies_tenant_id_index` (`tenant_id`),
  KEY `task_dependencies_created_by_index` (`created_by`),
  KEY `task_dependencies_updated_by_index` (`updated_by`),
  CONSTRAINT `task_dependencies_dependency_id_foreign` FOREIGN KEY (`dependency_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_dependencies_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_dependencies_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `task_dependencies`
--

LOCK TABLES `task_dependencies` WRITE;
/*!40000 ALTER TABLE `task_dependencies` DISABLE KEYS */;
/*!40000 ALTER TABLE `task_dependencies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `task_watchers`
--

DROP TABLE IF EXISTS `task_watchers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_watchers` (
  `id` char(26) NOT NULL,
  `task_id` char(26) NOT NULL,
  `user_id` char(26) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_task_watcher` (`task_id`,`user_id`),
  KEY `task_watchers_task_id_user_id_index` (`task_id`,`user_id`),
  KEY `task_watchers_user_id_task_id_index` (`user_id`,`task_id`),
  KEY `task_watchers_created_by_index` (`created_by`),
  KEY `task_watchers_updated_by_index` (`updated_by`),
  CONSTRAINT `task_watchers_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_watchers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `task_watchers`
--

LOCK TABLES `task_watchers` WRITE;
/*!40000 ALTER TABLE `task_watchers` DISABLE KEYS */;
/*!40000 ALTER TABLE `task_watchers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tasks`
--

DROP TABLE IF EXISTS `tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tasks` (
  `id` char(26) NOT NULL,
  `tenant_id` varchar(255) DEFAULT NULL,
  `project_id` varchar(255) NOT NULL,
  `component_id` varchar(255) DEFAULT NULL,
  `phase_id` varchar(255) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'todo',
  `priority` varchar(255) NOT NULL DEFAULT 'medium',
  `is_hidden` tinyint(1) NOT NULL DEFAULT 0,
  `visibility` varchar(255) NOT NULL DEFAULT 'team',
  `client_approved` tinyint(1) NOT NULL DEFAULT 0,
  `assignee_id` varchar(255) DEFAULT NULL,
  `assigned_to` varchar(255) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `progress_percent` int(11) NOT NULL DEFAULT 0,
  `estimated_hours` decimal(8,2) DEFAULT NULL,
  `actual_hours` decimal(8,2) DEFAULT NULL,
  `estimated_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `actual_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `risk_level` enum('low','medium','high','critical') NOT NULL DEFAULT 'low',
  `complexity` enum('simple','moderate','complex','very_complex') NOT NULL DEFAULT 'moderate',
  `effort_points` int(11) NOT NULL DEFAULT 1,
  `last_activity_at` timestamp NULL DEFAULT NULL,
  `time_spent` decimal(8,2) NOT NULL DEFAULT 0.00,
  `is_billable` tinyint(1) NOT NULL DEFAULT 1,
  `spent_hours` decimal(8,2) DEFAULT NULL,
  `parent_id` varchar(255) DEFAULT NULL,
  `order` int(11) NOT NULL DEFAULT 0,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `dependencies` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dependencies`)),
  `conditional_tag` varchar(255) DEFAULT NULL,
  `created_by` varchar(255) DEFAULT NULL,
  `updated_by` varchar(255) DEFAULT NULL,
  `watchers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`watchers`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tasks_project_id_status_index` (`project_id`,`status`),
  KEY `tasks_assignee_id_status_index` (`assignee_id`,`status`),
  KEY `tasks_start_date_end_date_index` (`start_date`,`end_date`),
  KEY `idx_tasks_tenant_id` (`tenant_id`,`id`),
  KEY `tasks_created_by_foreign` (`created_by`),
  KEY `tasks_updated_by_foreign` (`updated_by`),
  KEY `tasks_component_id_status_index` (`component_id`,`status`),
  KEY `tasks_assigned_to_status_index` (`assigned_to`,`status`),
  KEY `tasks_parent_id_index` (`parent_id`),
  KEY `tasks_is_hidden_index` (`is_hidden`),
  KEY `tasks_visibility_index` (`visibility`),
  KEY `tasks_project_status_priority_index` (`project_id`,`status`,`priority`),
  KEY `tasks_assignee_status_index` (`assignee_id`,`status`),
  KEY `tasks_tenant_status_index` (`tenant_id`,`status`),
  KEY `tasks_date_range_index` (`start_date`,`end_date`),
  KEY `tasks_progress_status_index` (`progress_percent`,`status`),
  KEY `tasks_hours_index` (`estimated_hours`,`actual_hours`),
  KEY `idx_tasks_assignee_status_updated` (`assigned_to`,`status`,`updated_at`),
  KEY `idx_tasks_project_status` (`project_id`,`status`),
  KEY `idx_tasks_status_updated` (`status`,`updated_at`),
  KEY `idx_tasks_priority_created` (`priority`,`created_at`),
  KEY `tasks_tenant_id_index` (`tenant_id`),
  KEY `tasks_tenant_id_project_id_index` (`tenant_id`,`project_id`),
  CONSTRAINT `tasks_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tasks_assignee_id_foreign` FOREIGN KEY (`assignee_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tasks_component_id_foreign` FOREIGN KEY (`component_id`) REFERENCES `zena_components` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tasks_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tasks_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tasks_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tasks_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tasks_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tasks`
--

LOCK TABLES `tasks` WRITE;
/*!40000 ALTER TABLE `tasks` DISABLE KEYS */;
/*!40000 ALTER TABLE `tasks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `team_members`
--

DROP TABLE IF EXISTS `team_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `team_members` (
  `team_id` char(26) NOT NULL,
  `user_id` char(26) NOT NULL,
  `role` varchar(255) NOT NULL DEFAULT 'member',
  `joined_at` timestamp NULL DEFAULT NULL,
  `left_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`team_id`,`user_id`),
  KEY `team_members_team_id_role_index` (`team_id`,`role`),
  KEY `team_members_user_id_team_id_index` (`user_id`,`team_id`),
  KEY `team_members_joined_at_index` (`joined_at`),
  KEY `team_members_left_at_index` (`left_at`),
  KEY `team_members_created_by_index` (`created_by`),
  KEY `team_members_updated_by_index` (`updated_by`),
  CONSTRAINT `team_members_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `team_members_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `team_members`
--

LOCK TABLES `team_members` WRITE;
/*!40000 ALTER TABLE `team_members` DISABLE KEYS */;
/*!40000 ALTER TABLE `team_members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teams`
--

DROP TABLE IF EXISTS `teams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teams` (
  `id` char(26) NOT NULL,
  `tenant_id` char(26) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `team_lead_id` char(26) DEFAULT NULL,
  `department` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`settings`)),
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_team_name_per_tenant` (`tenant_id`,`name`),
  KEY `teams_tenant_id_is_active_index` (`tenant_id`,`is_active`),
  KEY `teams_team_lead_id_index` (`team_lead_id`),
  KEY `teams_department_index` (`department`),
  KEY `teams_created_by_index` (`created_by`),
  KEY `teams_tenant_active_index` (`tenant_id`,`is_active`),
  KEY `teams_lead_active_index` (`team_lead_id`,`is_active`),
  KEY `teams_updated_by_index` (`updated_by`),
  CONSTRAINT `teams_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `teams_team_lead_id_foreign` FOREIGN KEY (`team_lead_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `teams_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `teams_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teams`
--

LOCK TABLES `teams` WRITE;
/*!40000 ALTER TABLE `teams` DISABLE KEYS */;
/*!40000 ALTER TABLE `teams` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `templates`
--

DROP TABLE IF EXISTS `templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `templates` (
  `id` char(26) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(255) NOT NULL DEFAULT 'general',
  `structure` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`structure`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `status` enum('draft','active','archived','deprecated') NOT NULL DEFAULT 'draft',
  `version` int(11) NOT NULL DEFAULT 1,
  `is_public` tinyint(1) NOT NULL DEFAULT 0,
  `usage_count` int(11) NOT NULL DEFAULT 0,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `template_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`template_data`)),
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`settings`)),
  `created_by` varchar(255) NOT NULL,
  `updated_by` varchar(255) DEFAULT NULL,
  `tenant_id` char(26) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `templates_tenant_id_category_index` (`tenant_id`,`category`),
  KEY `templates_tenant_id_is_active_index` (`tenant_id`,`is_active`),
  CONSTRAINT `templates_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `templates`
--

LOCK TABLES `templates` WRITE;
/*!40000 ALTER TABLE `templates` DISABLE KEYS */;
/*!40000 ALTER TABLE `templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenant_audit_logs`
--

DROP TABLE IF EXISTS `tenant_audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tenant_audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `action` varchar(255) NOT NULL,
  `tenant_id` varchar(255) DEFAULT NULL,
  `user_id` varchar(255) DEFAULT NULL,
  `ip_address` varchar(255) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `x_request_id` varchar(255) DEFAULT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tenant_audit_logs_tenant_id_created_at_index` (`tenant_id`,`created_at`),
  KEY `tenant_audit_logs_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `tenant_audit_logs_action_created_at_index` (`action`,`created_at`),
  KEY `tenant_audit_logs_x_request_id_index` (`x_request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_audit_logs`
--

LOCK TABLES `tenant_audit_logs` WRITE;
/*!40000 ALTER TABLE `tenant_audit_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `tenant_audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenant_subscriptions`
--

DROP TABLE IF EXISTS `tenant_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tenant_subscriptions` (
  `id` char(26) NOT NULL,
  `tenant_id` char(26) NOT NULL,
  `plan_id` char(26) NOT NULL,
  `status` enum('active','canceled','suspended','expired','trial') NOT NULL DEFAULT 'trial',
  `billing_cycle` enum('monthly','yearly') NOT NULL DEFAULT 'monthly',
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `started_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `renew_at` timestamp NULL DEFAULT NULL,
  `canceled_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `stripe_subscription_id` varchar(255) DEFAULT NULL,
  `stripe_customer_id` varchar(255) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tenant_subscriptions_tenant_id_status_index` (`tenant_id`,`status`),
  KEY `tenant_subscriptions_plan_id_status_index` (`plan_id`,`status`),
  KEY `tenant_subscriptions_status_renew_at_index` (`status`,`renew_at`),
  KEY `tenant_subscriptions_stripe_subscription_id_index` (`stripe_subscription_id`),
  KEY `tenant_subscriptions_created_by_index` (`created_by`),
  KEY `tenant_subscriptions_updated_by_index` (`updated_by`),
  CONSTRAINT `tenant_subscriptions_plan_id_foreign` FOREIGN KEY (`plan_id`) REFERENCES `billing_plans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tenant_subscriptions_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_subscriptions`
--

LOCK TABLES `tenant_subscriptions` WRITE;
/*!40000 ALTER TABLE `tenant_subscriptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `tenant_subscriptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenants`
--

DROP TABLE IF EXISTS `tenants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tenants` (
  `id` char(26) NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `domain` varchar(255) DEFAULT NULL,
  `database_name` varchar(255) DEFAULT NULL,
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`settings`)),
  `status` varchar(255) NOT NULL DEFAULT 'trial',
  `plan` enum('basic','professional','enterprise') NOT NULL DEFAULT 'basic',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `trial_ends_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `preferences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`preferences`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenants_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenants`
--

LOCK TABLES `tenants` WRITE;
/*!40000 ALTER TABLE `tenants` DISABLE KEYS */;
/*!40000 ALTER TABLE `tenants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_dashboards`
--

DROP TABLE IF EXISTS `user_dashboards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_dashboards` (
  `id` char(26) NOT NULL,
  `user_id` char(26) NOT NULL,
  `tenant_id` char(26) NOT NULL,
  `name` varchar(255) NOT NULL,
  `layout_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`layout_config`)),
  `widgets` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`widgets`)),
  `preferences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`preferences`)),
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_dashboards_tenant_id_foreign` (`tenant_id`),
  KEY `user_dashboards_user_id_tenant_id_index` (`user_id`,`tenant_id`),
  KEY `user_dashboards_is_default_is_active_index` (`is_default`,`is_active`),
  KEY `user_dashboards_created_by_index` (`created_by`),
  KEY `user_dashboards_updated_by_index` (`updated_by`),
  CONSTRAINT `user_dashboards_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_dashboards_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_dashboards`
--

LOCK TABLES `user_dashboards` WRITE;
/*!40000 ALTER TABLE `user_dashboards` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_dashboards` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_preferences`
--

DROP TABLE IF EXISTS `user_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_preferences` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` varchar(255) NOT NULL,
  `preferences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`preferences`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_preferences_user_id_unique` (`user_id`),
  KEY `user_preferences_user_id_index` (`user_id`),
  KEY `user_preferences_created_by_index` (`created_by`),
  KEY `user_preferences_updated_by_index` (`updated_by`),
  CONSTRAINT `user_preferences_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_preferences`
--

LOCK TABLES `user_preferences` WRITE;
/*!40000 ALTER TABLE `user_preferences` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_preferences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_roles`
--

DROP TABLE IF EXISTS `user_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_roles` (
  `user_id` char(26) NOT NULL,
  `role_id` char(26) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`user_id`,`role_id`),
  KEY `user_roles_created_by_index` (`created_by`),
  KEY `user_roles_updated_by_index` (`updated_by`),
  KEY `user_roles_role_id_foreign` (`role_id`),
  CONSTRAINT `user_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `zena_roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `zena_user_roles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_roles`
--

LOCK TABLES `user_roles` WRITE;
/*!40000 ALTER TABLE `user_roles` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_sessions`
--

DROP TABLE IF EXISTS `user_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` varchar(255) NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `device_id` varchar(255) DEFAULT NULL,
  `device_name` varchar(255) DEFAULT NULL,
  `device_type` varchar(255) DEFAULT NULL,
  `browser` varchar(255) DEFAULT NULL,
  `browser_version` varchar(255) DEFAULT NULL,
  `os` varchar(255) DEFAULT NULL,
  `os_version` varchar(255) DEFAULT NULL,
  `ip_address` varchar(255) NOT NULL,
  `country` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT 0,
  `is_trusted` tinyint(1) NOT NULL DEFAULT 0,
  `last_activity_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` char(26) DEFAULT NULL,
  `updated_by` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_sessions_session_id_unique` (`session_id`),
  KEY `user_sessions_user_id_is_current_index` (`user_id`,`is_current`),
  KEY `user_sessions_user_id_last_activity_at_index` (`user_id`,`last_activity_at`),
  KEY `user_sessions_expires_at_index` (`expires_at`),
  KEY `user_sessions_created_by_index` (`created_by`),
  KEY `user_sessions_updated_by_index` (`updated_by`),
  CONSTRAINT `user_sessions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_sessions`
--

LOCK TABLES `user_sessions` WRITE;
/*!40000 ALTER TABLE `user_sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` char(26) NOT NULL,
  `tenant_id` char(26) DEFAULT NULL,
  `current_project_id` char(26) DEFAULT NULL,
  `profile_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`profile_data`)),
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `email_notifications_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `email_verification_token` varchar(255) DEFAULT NULL,
  `email_verification_token_expires_at` timestamp NULL DEFAULT NULL,
  `pending_email` varchar(255) DEFAULT NULL,
  `email_change_token` varchar(255) DEFAULT NULL,
  `email_change_token_expires_at` timestamp NULL DEFAULT NULL,
  `mfa_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `mfa_secret` varchar(255) DEFAULT NULL,
  `mfa_recovery_codes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`mfa_recovery_codes`)),
  `mfa_enabled_at` timestamp NULL DEFAULT NULL,
  `mfa_backup_codes_used` int(11) NOT NULL DEFAULT 0,
  `password_changed_at` timestamp NULL DEFAULT NULL,
  `password_expires_at` timestamp NULL DEFAULT NULL,
  `password_failed_attempts` int(11) NOT NULL DEFAULT 0,
  `password_locked_until` timestamp NULL DEFAULT NULL,
  `password_history` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`password_history`)),
  `oidc_provider` varchar(255) DEFAULT NULL,
  `oidc_subject_id` varchar(255) DEFAULT NULL,
  `oidc_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`oidc_data`)),
  `saml_provider` varchar(255) DEFAULT NULL,
  `saml_name_id` varchar(255) DEFAULT NULL,
  `saml_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`saml_data`)),
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `department` varchar(255) DEFAULT NULL,
  `job_title` varchar(255) DEFAULT NULL,
  `manager` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `preferences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`preferences`)),
  `last_login_at` timestamp NULL DEFAULT NULL,
  `login_count` int(11) NOT NULL DEFAULT 0,
  `failed_login_attempts` int(11) NOT NULL DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_super_admin` tinyint(1) NOT NULL DEFAULT 0,
  `role` varchar(255) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `organization_id` bigint(20) unsigned DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','pending','suspended') NOT NULL DEFAULT 'pending',
  `invitation_id` bigint(20) unsigned DEFAULT NULL,
  `invited_at` timestamp NULL DEFAULT NULL,
  `joined_at` timestamp NULL DEFAULT NULL,
  `timezone` varchar(255) NOT NULL DEFAULT 'UTC',
  `locale` varchar(10) NOT NULL DEFAULT 'en',
  `language` varchar(255) NOT NULL DEFAULT 'en',
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `ux_users_email_tenant` (`email`,`tenant_id`),
  KEY `users_email_index` (`email`),
  KEY `users_tenant_id_index` (`tenant_id`),
  KEY `users_email_verification_token_index` (`email_verification_token`),
  KEY `users_email_change_token_index` (`email_change_token`),
  KEY `users_mfa_enabled_index` (`mfa_enabled`),
  KEY `users_password_expires_at_index` (`password_expires_at`),
  KEY `users_password_locked_until_index` (`password_locked_until`),
  KEY `users_oidc_provider_index` (`oidc_provider`),
  KEY `users_oidc_subject_id_index` (`oidc_subject_id`),
  KEY `users_saml_provider_index` (`saml_provider`),
  KEY `users_saml_name_id_index` (`saml_name_id`),
  KEY `users_organization_id_status_index` (`organization_id`,`status`),
  KEY `users_email_verified_at_index` (`email_verified_at`),
  KEY `users_status_tenant_id_index` (`status`,`tenant_id`),
  KEY `users_role_status_index` (`role`,`status`),
  KEY `users_created_at_index` (`created_at`),
  KEY `users_tenant_status_index` (`tenant_id`,`status`),
  KEY `idx_users_tenant_active` (`tenant_id`,`is_active`),
  KEY `idx_users_role_active` (`role`,`is_active`),
  KEY `idx_users_last_login` (`last_login_at`),
  KEY `users_is_active_index` (`is_active`),
  KEY `users_mfa_secret_index` (`mfa_secret`),
  KEY `users_last_login_at_index` (`last_login_at`),
  KEY `users_role_index` (`role`),
  KEY `users_current_project_id_foreign` (`current_project_id`),
  CONSTRAINT `users_current_project_id_foreign` FOREIGN KEY (`current_project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `widgets`
--

DROP TABLE IF EXISTS `widgets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `widgets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`settings`)),
  `dashboard_id` bigint(20) unsigned NOT NULL,
  `user_id` char(26) DEFAULT NULL,
  `tenant_id` char(26) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `widgets_user_id_foreign` (`user_id`),
  KEY `widgets_dashboard_id_created_at_index` (`dashboard_id`,`created_at`),
  KEY `widgets_tenant_id_foreign` (`tenant_id`),
  CONSTRAINT `widgets_dashboard_id_foreign` FOREIGN KEY (`dashboard_id`) REFERENCES `dashboards` (`id`) ON DELETE CASCADE,
  CONSTRAINT `widgets_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `widgets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `widgets`
--

LOCK TABLES `widgets` WRITE;
/*!40000 ALTER TABLE `widgets` DISABLE KEYS */;
/*!40000 ALTER TABLE `widgets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `zena_change_requests`
--

DROP TABLE IF EXISTS `zena_change_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `zena_change_requests` (
  `id` char(26) NOT NULL,
  `deprecated_notice` varchar(255) DEFAULT NULL,
  `project_id` char(26) NOT NULL,
  `title` varchar(255) NOT NULL,
  `reason` text NOT NULL,
  `impact_description` text DEFAULT NULL,
  `impact_cost` decimal(15,2) DEFAULT NULL,
  `impact_time_days` int(11) DEFAULT NULL,
  `status` enum('draft','submitted','under_review','approved','rejected') NOT NULL DEFAULT 'draft',
  `requested_by` char(26) NOT NULL,
  `reviewed_by` char(26) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `review_comments` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `zena_change_requests_requested_by_foreign` (`requested_by`),
  KEY `zena_change_requests_reviewed_by_foreign` (`reviewed_by`),
  KEY `zena_change_requests_project_id_status_index` (`project_id`,`status`),
  CONSTRAINT `zena_change_requests_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `zena_projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `zena_change_requests_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `zena_change_requests_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `zena_change_requests`
--

LOCK TABLES `zena_change_requests` WRITE;
/*!40000 ALTER TABLE `zena_change_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `zena_change_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `zena_components`
--

DROP TABLE IF EXISTS `zena_components`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `zena_components` (
  `id` char(26) NOT NULL,
  `deprecated_notice` varchar(255) DEFAULT NULL,
  `project_id` char(26) NOT NULL,
  `parent_id` char(26) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'component',
  `progress` decimal(5,2) NOT NULL DEFAULT 0.00,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `budget` decimal(15,2) DEFAULT NULL,
  `actual_cost` decimal(15,2) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `zena_components_project_id_type_index` (`project_id`,`type`),
  KEY `zena_components_parent_id_index` (`parent_id`),
  CONSTRAINT `zena_components_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `zena_components` (`id`) ON DELETE CASCADE,
  CONSTRAINT `zena_components_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `zena_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `zena_components`
--

LOCK TABLES `zena_components` WRITE;
/*!40000 ALTER TABLE `zena_components` DISABLE KEYS */;
/*!40000 ALTER TABLE `zena_components` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `zena_drawings`
--

DROP TABLE IF EXISTS `zena_drawings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `zena_drawings` (
  `id` char(26) NOT NULL,
  `deprecated_notice` varchar(255) DEFAULT NULL,
  `project_id` char(26) NOT NULL,
  `code` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `version` varchar(255) NOT NULL DEFAULT '1.0',
  `status` enum('draft','review','approved','issued') NOT NULL DEFAULT 'draft',
  `file_url` varchar(255) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `uploaded_by` char(26) NOT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `zena_drawings_uploaded_by_foreign` (`uploaded_by`),
  KEY `zena_drawings_project_id_status_index` (`project_id`,`status`),
  CONSTRAINT `zena_drawings_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `zena_projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `zena_drawings_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `zena_drawings`
--

LOCK TABLES `zena_drawings` WRITE;
/*!40000 ALTER TABLE `zena_drawings` DISABLE KEYS */;
/*!40000 ALTER TABLE `zena_drawings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `zena_invoices`
--

DROP TABLE IF EXISTS `zena_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `zena_invoices` (
  `id` char(26) NOT NULL,
  `project_id` char(26) NOT NULL,
  `invoice_number` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `status` enum('draft','sent','approved','paid','cancelled') NOT NULL DEFAULT 'draft',
  `due_date` date NOT NULL,
  `created_by` char(26) NOT NULL,
  `approved_by` char(26) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `zena_invoices_created_by_foreign` (`created_by`),
  KEY `zena_invoices_approved_by_foreign` (`approved_by`),
  KEY `zena_invoices_project_id_status_index` (`project_id`,`status`),
  CONSTRAINT `zena_invoices_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `zena_invoices_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `zena_invoices_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `zena_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `zena_invoices`
--

LOCK TABLES `zena_invoices` WRITE;
/*!40000 ALTER TABLE `zena_invoices` DISABLE KEYS */;
/*!40000 ALTER TABLE `zena_invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `zena_material_requests`
--

DROP TABLE IF EXISTS `zena_material_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `zena_material_requests` (
  `id` char(26) NOT NULL,
  `project_id` char(26) NOT NULL,
  `request_number` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `status` enum('draft','submitted','approved','rejected','fulfilled') NOT NULL DEFAULT 'draft',
  `estimated_cost` decimal(15,2) DEFAULT NULL,
  `required_date` date DEFAULT NULL,
  `requested_by` char(26) NOT NULL,
  `approved_by` char(26) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `zena_material_requests_requested_by_foreign` (`requested_by`),
  KEY `zena_material_requests_approved_by_foreign` (`approved_by`),
  KEY `zena_material_requests_project_id_status_index` (`project_id`,`status`),
  CONSTRAINT `zena_material_requests_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `zena_material_requests_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `zena_projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `zena_material_requests_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `zena_material_requests`
--

LOCK TABLES `zena_material_requests` WRITE;
/*!40000 ALTER TABLE `zena_material_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `zena_material_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `zena_ncrs`
--

DROP TABLE IF EXISTS `zena_ncrs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `zena_ncrs` (
  `id` char(26) NOT NULL,
  `project_id` char(26) NOT NULL,
  `ncr_number` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `status` enum('open','under_review','closed') NOT NULL DEFAULT 'open',
  `severity` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `created_by` char(26) NOT NULL,
  `assigned_to` char(26) DEFAULT NULL,
  `resolution` text DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `zena_ncrs_created_by_foreign` (`created_by`),
  KEY `zena_ncrs_assigned_to_foreign` (`assigned_to`),
  KEY `zena_ncrs_project_id_status_index` (`project_id`,`status`),
  CONSTRAINT `zena_ncrs_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `zena_ncrs_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `zena_ncrs_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `zena_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `zena_ncrs`
--

LOCK TABLES `zena_ncrs` WRITE;
/*!40000 ALTER TABLE `zena_ncrs` DISABLE KEYS */;
/*!40000 ALTER TABLE `zena_ncrs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `zena_permissions`
--

DROP TABLE IF EXISTS `zena_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `zena_permissions` (
  `id` char(26) NOT NULL,
  `code` varchar(255) NOT NULL,
  `module` varchar(255) NOT NULL,
  `action` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `zena_permissions_code_unique` (`code`),
  KEY `zena_permissions_module_action_index` (`module`,`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `zena_permissions`
--

LOCK TABLES `zena_permissions` WRITE;
/*!40000 ALTER TABLE `zena_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `zena_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `zena_project_users`
--

DROP TABLE IF EXISTS `zena_project_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `zena_project_users` (
  `id` char(26) NOT NULL,
  `project_id` char(26) NOT NULL,
  `user_id` char(26) NOT NULL,
  `role_on_project` varchar(255) NOT NULL DEFAULT 'member',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `zena_project_users_project_id_user_id_unique` (`project_id`,`user_id`),
  KEY `zena_project_users_user_id_foreign` (`user_id`),
  CONSTRAINT `zena_project_users_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `zena_projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `zena_project_users_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `zena_project_users`
--

LOCK TABLES `zena_project_users` WRITE;
/*!40000 ALTER TABLE `zena_project_users` DISABLE KEYS */;
/*!40000 ALTER TABLE `zena_project_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `zena_projects`
--

DROP TABLE IF EXISTS `zena_projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `zena_projects` (
  `id` char(26) NOT NULL,
  `deprecated_notice` varchar(255) DEFAULT NULL,
  `tenant_id` char(26) DEFAULT NULL,
  `code` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `client_id` char(26) DEFAULT NULL,
  `status` enum('planning','active','on_hold','completed','cancelled') NOT NULL DEFAULT 'planning',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `budget` decimal(15,2) DEFAULT NULL,
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`settings`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `zena_projects_code_unique` (`code`),
  KEY `zena_projects_client_id_foreign` (`client_id`),
  KEY `zena_projects_status_index` (`status`),
  KEY `zena_projects_tenant_id_index` (`tenant_id`),
  CONSTRAINT `zena_projects_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `zena_projects_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `zena_projects`
--

LOCK TABLES `zena_projects` WRITE;
/*!40000 ALTER TABLE `zena_projects` DISABLE KEYS */;
/*!40000 ALTER TABLE `zena_projects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `zena_purchase_orders`
--

DROP TABLE IF EXISTS `zena_purchase_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `zena_purchase_orders` (
  `id` char(26) NOT NULL,
  `project_id` char(26) NOT NULL,
  `po_number` varchar(255) NOT NULL,
  `vendor_name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `status` enum('draft','sent','approved','received','cancelled') NOT NULL DEFAULT 'draft',
  `total_amount` decimal(15,2) NOT NULL,
  `due_date` date DEFAULT NULL,
  `created_by` char(26) NOT NULL,
  `approved_by` char(26) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `zena_purchase_orders_created_by_foreign` (`created_by`),
  KEY `zena_purchase_orders_approved_by_foreign` (`approved_by`),
  KEY `zena_purchase_orders_project_id_status_index` (`project_id`,`status`),
  CONSTRAINT `zena_purchase_orders_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `zena_purchase_orders_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `zena_purchase_orders_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `zena_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `zena_purchase_orders`
--

LOCK TABLES `zena_purchase_orders` WRITE;
/*!40000 ALTER TABLE `zena_purchase_orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `zena_purchase_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `zena_qc_inspections`
--

DROP TABLE IF EXISTS `zena_qc_inspections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `zena_qc_inspections` (
  `id` char(26) NOT NULL,
  `qc_plan_id` char(26) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `status` enum('scheduled','in_progress','completed','failed') NOT NULL DEFAULT 'scheduled',
  `inspection_date` date NOT NULL,
  `inspector_id` char(26) NOT NULL,
  `findings` text DEFAULT NULL,
  `recommendations` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `zena_qc_inspections_inspector_id_foreign` (`inspector_id`),
  KEY `zena_qc_inspections_qc_plan_id_status_index` (`qc_plan_id`,`status`),
  CONSTRAINT `zena_qc_inspections_inspector_id_foreign` FOREIGN KEY (`inspector_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `zena_qc_inspections_qc_plan_id_foreign` FOREIGN KEY (`qc_plan_id`) REFERENCES `zena_qc_plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `zena_qc_inspections`
--

LOCK TABLES `zena_qc_inspections` WRITE;
/*!40000 ALTER TABLE `zena_qc_inspections` DISABLE KEYS */;
/*!40000 ALTER TABLE `zena_qc_inspections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `zena_qc_plans`
--

DROP TABLE IF EXISTS `zena_qc_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `zena_qc_plans` (
  `id` char(26) NOT NULL,
  `project_id` char(26) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `status` enum('draft','active','completed') NOT NULL DEFAULT 'draft',
  `planned_date` date NOT NULL,
  `created_by` char(26) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `zena_qc_plans_created_by_foreign` (`created_by`),
  KEY `zena_qc_plans_project_id_status_index` (`project_id`,`status`),
  CONSTRAINT `zena_qc_plans_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `zena_qc_plans_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `zena_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `zena_qc_plans`
--

LOCK TABLES `zena_qc_plans` WRITE;
/*!40000 ALTER TABLE `zena_qc_plans` DISABLE KEYS */;
/*!40000 ALTER TABLE `zena_qc_plans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `zena_rfis`
--

DROP TABLE IF EXISTS `zena_rfis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `zena_rfis` (
  `id` char(26) NOT NULL,
  `deprecated_notice` varchar(255) DEFAULT NULL,
  `project_id` char(26) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `question` text NOT NULL,
  `asked_by` char(26) NOT NULL,
  `assigned_to` char(26) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('open','answered','closed') NOT NULL DEFAULT 'open',
  `answer` text DEFAULT NULL,
  `answered_by` char(26) DEFAULT NULL,
  `answered_at` timestamp NULL DEFAULT NULL,
  `attachments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attachments`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `zena_rfis_asked_by_foreign` (`asked_by`),
  KEY `zena_rfis_assigned_to_foreign` (`assigned_to`),
  KEY `zena_rfis_answered_by_foreign` (`answered_by`),
  KEY `zena_rfis_project_id_status_index` (`project_id`,`status`),
  CONSTRAINT `zena_rfis_answered_by_foreign` FOREIGN KEY (`answered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `zena_rfis_asked_by_foreign` FOREIGN KEY (`asked_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `zena_rfis_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `zena_rfis_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `zena_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `zena_rfis`
--

LOCK TABLES `zena_rfis` WRITE;
/*!40000 ALTER TABLE `zena_rfis` DISABLE KEYS */;
/*!40000 ALTER TABLE `zena_rfis` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `zena_role_permissions`
--

DROP TABLE IF EXISTS `zena_role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `zena_role_permissions` (
  `role_id` char(26) NOT NULL,
  `permission_id` char(26) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`role_id`,`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `zena_role_permissions`
--

LOCK TABLES `zena_role_permissions` WRITE;
/*!40000 ALTER TABLE `zena_role_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `zena_role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `zena_roles`
--

DROP TABLE IF EXISTS `zena_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `zena_roles` (
  `id` char(26) NOT NULL,
  `name` varchar(255) NOT NULL,
  `scope` varchar(255) NOT NULL DEFAULT 'system',
  `description` text DEFAULT NULL,
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `zena_roles_name_scope_index` (`name`,`scope`),
  KEY `zena_roles_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `zena_roles`
--

LOCK TABLES `zena_roles` WRITE;
/*!40000 ALTER TABLE `zena_roles` DISABLE KEYS */;
/*!40000 ALTER TABLE `zena_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `zena_submittals`
--

DROP TABLE IF EXISTS `zena_submittals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `zena_submittals` (
  `id` char(26) NOT NULL,
  `deprecated_notice` varchar(255) DEFAULT NULL,
  `project_id` char(26) NOT NULL,
  `package_no` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('draft','submitted','under_review','approved','rejected') NOT NULL DEFAULT 'draft',
  `due_date` date DEFAULT NULL,
  `file_url` varchar(255) DEFAULT NULL,
  `submitted_by` char(26) NOT NULL,
  `reviewed_by` char(26) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `review_comments` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `zena_submittals_submitted_by_foreign` (`submitted_by`),
  KEY `zena_submittals_reviewed_by_foreign` (`reviewed_by`),
  KEY `zena_submittals_project_id_status_index` (`project_id`,`status`),
  CONSTRAINT `zena_submittals_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `zena_projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `zena_submittals_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `zena_submittals_submitted_by_foreign` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `zena_submittals`
--

LOCK TABLES `zena_submittals` WRITE;
/*!40000 ALTER TABLE `zena_submittals` DISABLE KEYS */;
/*!40000 ALTER TABLE `zena_submittals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `zena_task_assignments`
--

DROP TABLE IF EXISTS `zena_task_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `zena_task_assignments` (
  `id` char(26) NOT NULL,
  `deprecated_notice` varchar(255) DEFAULT NULL,
  `task_id` char(26) NOT NULL,
  `user_id` char(26) NOT NULL,
  `role` varchar(255) NOT NULL DEFAULT 'assignee',
  `assigned_hours` decimal(8,2) DEFAULT NULL,
  `actual_hours` decimal(8,2) NOT NULL DEFAULT 0.00,
  `status` varchar(255) NOT NULL DEFAULT 'assigned',
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `zena_task_assignments_task_id_user_id_unique` (`task_id`,`user_id`),
  KEY `zena_task_assignments_user_id_status_index` (`user_id`,`status`),
  CONSTRAINT `zena_task_assignments_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `zena_tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `zena_task_assignments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `zena_task_assignments`
--

LOCK TABLES `zena_task_assignments` WRITE;
/*!40000 ALTER TABLE `zena_task_assignments` DISABLE KEYS */;
/*!40000 ALTER TABLE `zena_task_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `zena_tasks`
--

DROP TABLE IF EXISTS `zena_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `zena_tasks` (
  `id` char(26) NOT NULL,
  `deprecated_notice` varchar(255) DEFAULT NULL,
  `project_id` char(26) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `assignee_id` char(26) DEFAULT NULL,
  `status` enum('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `priority` enum('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `start_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `progress` int(11) NOT NULL DEFAULT 0,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `zena_tasks_project_id_status_index` (`project_id`,`status`),
  KEY `zena_tasks_assignee_id_status_index` (`assignee_id`,`status`),
  CONSTRAINT `zena_tasks_assignee_id_foreign` FOREIGN KEY (`assignee_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `zena_tasks_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `zena_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `zena_tasks`
--

LOCK TABLES `zena_tasks` WRITE;
/*!40000 ALTER TABLE `zena_tasks` DISABLE KEYS */;
/*!40000 ALTER TABLE `zena_tasks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'zenamanage_testing'
--
