-- Template SQL for new company databases
-- Generated from rsgeotech schema
-- This file contains structure only (no data)

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- --------------------------------------------------------

CREATE TABLE `activity` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `uid` int(11) DEFAULT NULL,
  `ref_id` int(11) NOT NULL,
  `ref_table` varchar(250) NOT NULL,
  `module_id` int(11) NOT NULL,
  `action` varchar(500) NOT NULL,
  `date` date NOT NULL,
  `time` varchar(250) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

CREATE TABLE `assets` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(500) NOT NULL,
  `head_id` int(11) NOT NULL,
  `cost_price` varchar(500) NOT NULL,
  `create_datetime` datetime NOT NULL DEFAULT current_timestamp(),
  `site_id` varchar(200) NOT NULL,
  `status` varchar(200) NOT NULL DEFAULT 'Working',
  `expense_id` varchar(200) DEFAULT NULL,
  `sale_price` varchar(250) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

CREATE TABLE `assets_expense_head` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `head_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

CREATE TABLE `asset_head` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(250) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

CREATE TABLE `asset_transaction` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `asset_id` int(11) NOT NULL,
  `from_site` int(11) DEFAULT NULL,
  `to_site` int(11) DEFAULT NULL,
  `transaction_type` varchar(500) NOT NULL,
  `remark` varchar(2000) DEFAULT NULL,
  `create_datetime` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

CREATE TABLE `bills_party` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `address` varchar(500) DEFAULT NULL,
  `panno` varchar(50) DEFAULT NULL,
  `bank_ac` varchar(50) DEFAULT NULL,
  `ifsc` varchar(50) DEFAULT NULL,
  `bankname` varchar(50) DEFAULT NULL,
  `status` varchar(255) DEFAULT 'Pending',
  `site_id` varchar(50) DEFAULT NULL,
  `ac_holder_name` varchar(2000) DEFAULT NULL,
  `cost_category_id` int(11) DEFAULT NULL,
  `create_datetime` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

CREATE TABLE `bills_rate` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `work_id` varchar(255) DEFAULT NULL,
  `rate` varchar(255) NOT NULL,
  `site_id` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

CREATE TABLE `bills_work` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `unit` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

CREATE TABLE `bill_party_payments` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `party_id` int(11) NOT NULL,
  `amount` varchar(250) NOT NULL,
  `remark` varchar(2000) DEFAULT NULL,
  `date` date NOT NULL,
  `create_datetime` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

CREATE TABLE `bill_party_statement` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `party_id` int(11) NOT NULL,
  `type` varchar(200) NOT NULL,
  `particular` varchar(2000) NOT NULL,
  `bill_no` varchar(250) DEFAULT NULL,
  `create_datetime` datetime NOT NULL DEFAULT current_timestamp(),
  `expense_id` varchar(200) DEFAULT NULL,
  `payment_id` int(11) DEFAULT NULL,
  `payment_voucher_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

CREATE TABLE `contact` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `profile_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

CREATE TABLE `contact_profile` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `comp_name` varchar(255) NOT NULL,
  `contact_name` varchar(255) NOT NULL,
  `mobile` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `category` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

CREATE TABLE `data_time` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `type` varchar(250) NOT NULL,
  `from_date` varchar(250) NOT NULL,
  `to_date` varchar(250) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

CREATE TABLE `doc_head` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

CREATE TABLE `doc_head_option` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `head_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

CREATE TABLE `doc_meta` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `doc_id` int(11) NOT NULL,
  `head_id` int(11) NOT NULL,
  `option_id` int(11) NOT NULL,
  `structure` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

CREATE TABLE `doc_upload` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `path` varchar(255) NOT NULL,
  `date` date DEFAULT NULL,
  `particular` varchar(250) DEFAULT NULL,
  `remark` varchar(500) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `status` varchar(250) NOT NULL DEFAULT 'Pending',
  `create_datetime` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `party_id` varchar(255) NOT NULL,
  `party_type` varchar(250) NOT NULL,
  `head_id` varchar(255) NOT NULL,
  `particular` varchar(255) DEFAULT NULL,
  `amount` varchar(255) DEFAULT NULL,
  `remark` varchar(1000) DEFAULT NULL,
  `image` varchar(1000) DEFAULT NULL,
  `site_id` varchar(255) DEFAULT NULL,
  `user_id` varchar(255) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Pending',
  `location` varchar(500) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `asset_head` int(11) DEFAULT NULL,
  `machinery_head` int(11) DEFAULT NULL,
  `create_datetime` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

CREATE TABLE `expense_head` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

CREATE TABLE `expense_party` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `address` varchar(500) DEFAULT NULL,
  `pan_no` varchar(15) DEFAULT NULL,
  `site_id` varchar(50) DEFAULT NULL,
  `cost_category_id` int(11) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'Pending',
  `create_datetime` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

CREATE TABLE `machinery_details` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(500) NOT NULL,
  `head_id` int(11) DEFAULT NULL,
  `status` varchar(250) NOT NULL DEFAULT 'Working',
  `site_id` varchar(200) NOT NULL,
  `expense_id` int(11) DEFAULT NULL,
  `create_datetime` datetime NOT NULL DEFAULT current_timestamp(),
  `cost_price` varchar(250) DEFAULT NULL,
  `sale_price` varchar(250) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

CREATE TABLE `machinery_documents` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `machinery_id` int(11) NOT NULL,
  `name` varchar(500) NOT NULL,
  `issue_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `create_date` datetime NOT NULL DEFAULT current_timestamp(),
  `attachment` varchar(2000) NOT NULL,
  `remark` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

CREATE TABLE `machinery_expense_head` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `head_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

CREATE TABLE `machinery_head` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(250) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

CREATE TABLE `machinery_services` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `machinery_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `maintainence_item` varchar(2000) NOT NULL,
  `create_date` datetime NOT NULL DEFAULT current_timestamp(),
  `image1` varchar(500) NOT NULL,
  `image2` varchar(500) NOT NULL,
  `image3` varchar(500) DEFAULT NULL,
  `image4` varchar(500) DEFAULT NULL,
  `image5` varchar(500) DEFAULT NULL,
  `next_service_on` date DEFAULT NULL,
  `remark` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

CREATE TABLE `machinery_transaction` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `machinery_id` int(11) NOT NULL,
  `from_site` int(11) DEFAULT NULL,
  `to_site` int(11) DEFAULT NULL,
  `transaction_type` varchar(500) NOT NULL,
  `remark` varchar(2000) DEFAULT NULL,
  `create_datetime` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

CREATE TABLE `materials` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

CREATE TABLE `material_consumption` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `material_id` int(11) NOT NULL,
  `site_id` int(11) NOT NULL,
  `qty` varchar(250) NOT NULL,
  `unit` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` varchar(250) NOT NULL DEFAULT 'Pending',
  `location` varchar(2000) DEFAULT NULL,
  `image` varchar(2000) DEFAULT NULL,
  `remark` varchar(2000) DEFAULT NULL,
  `date` varchar(250) NOT NULL,
  `task_id` int(11) DEFAULT NULL,
  `create_datetime` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

CREATE TABLE `material_conversion_rules` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `material_id` int(11) NOT NULL,
  `from_unit` int(11) NOT NULL,
  `to_unit` int(11) NOT NULL,
  `conversion_factor` varchar(250) NOT NULL,
  `created_by` int(11) NOT NULL,
  `create_datetime` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

CREATE TABLE `material_entry` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `supplier` varchar(255) NOT NULL,
  `material_id` varchar(255) NOT NULL,
  `unit` varchar(255) NOT NULL,
  `qty` varchar(255) NOT NULL,
  `vehical` varchar(255) NOT NULL,
  `image` varchar(1000) DEFAULT NULL,
  `image2` varchar(2000) DEFAULT NULL,
  `remark` varchar(2000) DEFAULT NULL,
  `location` varchar(500) DEFAULT NULL,
  `site_id` varchar(255) NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Pending',
  `user_id` varchar(255) DEFAULT NULL,
  `rate` varchar(200) DEFAULT NULL,
  `amount` varchar(200) DEFAULT NULL,
  `tax` varchar(250) DEFAULT NULL,
  `bill_no` varchar(200) DEFAULT NULL,
  `date` date NOT NULL,
  `create_datetime` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

CREATE TABLE `material_reconsilation_data` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `reconsilation_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `system_qty` varchar(250) NOT NULL,
  `reconsiled_qty` varchar(250) DEFAULT NULL,
  `unit` int(11) NOT NULL,
  `difference` varchar(250) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

CREATE TABLE `material_reconsilation_record` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `site_id` int(11) NOT NULL,
  `requested_by` int(11) NOT NULL,
  `upload_by` int(11) DEFAULT NULL,
  `date` varchar(250) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `status` varchar(250) NOT NULL DEFAULT 'Pending',
  `stock_updated` enum('Yes','No') NOT NULL DEFAULT 'No',
  `create_datetime` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

CREATE TABLE `material_site_transfers` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `material_id` int(11) NOT NULL,
  `qty` varchar(250) NOT NULL,
  `unit` int(11) NOT NULL,
  `from_site` int(11) NOT NULL,
  `to_site` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `date` varchar(250) NOT NULL,
  `vehicle_no` varchar(250) DEFAULT NULL,
  `remark` varchar(2000) DEFAULT NULL,
  `create_datetime` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

CREATE TABLE `material_stock_record` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `material_id` int(11) NOT NULL,
  `site_id` int(11) NOT NULL,
  `qty` varchar(250) NOT NULL,
  `unit` int(11) NOT NULL,
  `last_updated` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

CREATE TABLE `material_stock_transactions` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `site_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `qty` varchar(250) NOT NULL,
  `unit` int(11) NOT NULL,
  `type` enum('IN','OUT') NOT NULL,
  `refrence` enum('Purchase','Consumption','Wastage','Site Transferred Debit','Site Transferred Credit','Unit Conversion Debit','Unit Conversion Credit','Reconcile Stock Debit','Reconcile Stock Credit') NOT NULL,
  `refrence_id` int(11) NOT NULL,
  `create_datetime` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

CREATE TABLE `material_supplier` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `gstin` varchar(20) DEFAULT NULL,
  `bank_ac` varchar(20) DEFAULT NULL,
  `bank_ifsc` varchar(20) DEFAULT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `bank_ac_holder` varchar(255) DEFAULT NULL,
  `cost_category_id` int(11) DEFAULT NULL,
  `status` varchar(250) NOT NULL DEFAULT 'Active',
  `create_datetime` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

CREATE TABLE `material_supplier_statement` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `supplier_id` int(11) NOT NULL,
  `type` varchar(250) NOT NULL,
  `entry_id` int(11) DEFAULT NULL,
  `payment_voucher_id` int(11) DEFAULT NULL,
  `create_datetime` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

CREATE TABLE `material_units_conversion_record` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `material_id` int(11) NOT NULL,
  `qty` varchar(250) NOT NULL,
  `from_unit` int(11) NOT NULL,
  `to_unit` int(11) NOT NULL,
  `updated_qty` varchar(250) NOT NULL,
  `user_id` int(11) NOT NULL,
  `site_id` int(11) NOT NULL,
  `date` varchar(250) NOT NULL,
  `remark` varchar(2000) DEFAULT NULL,
  `create_datetime` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

CREATE TABLE `material_wastage` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `material_id` int(11) NOT NULL,
  `site_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `qty` varchar(250) NOT NULL,
  `unit` int(11) NOT NULL,
  `date` varchar(250) NOT NULL,
  `location` varchar(2000) DEFAULT NULL,
  `remark` varchar(2000) DEFAULT NULL,
  `reason` varchar(2000) NOT NULL,
  `image` varchar(2000) DEFAULT NULL,
  `status` varchar(250) NOT NULL DEFAULT 'Pending',
  `create_datetime` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

CREATE TABLE `new_bills_item_entry` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `work_id` varchar(255) NOT NULL,
  `unit` varchar(255) NOT NULL,
  `rate` varchar(255) NOT NULL,
  `qty` varchar(255) NOT NULL,
  `amount` varchar(255) NOT NULL,
  `bill_id` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

CREATE TABLE `new_bill_entry` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `party_id` varchar(255) NOT NULL,
  `bill_no` varchar(255) NOT NULL,
  `site_id` varchar(255) NOT NULL,
  `billdate` varchar(255) NOT NULL,
  `bill_period` varchar(255) NOT NULL,
  `user_id` varchar(255) DEFAULT NULL,
  `location` varchar(500) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'Pending',
  `amount` varchar(250) NOT NULL,
  `create_datetime` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `remark` varchar(2000) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

CREATE TABLE `other_parties` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(250) NOT NULL,
  `panno` varchar(15) DEFAULT NULL,
  `address` varchar(500) DEFAULT NULL,
  `bank_ac` varchar(250) DEFAULT NULL,
  `bank_name` varchar(250) DEFAULT NULL,
  `bank_ac_holder` varchar(250) DEFAULT NULL,
  `bank_ifsc` varchar(250) DEFAULT NULL,
  `cost_category_id` int(11) DEFAULT NULL,
  `status` varchar(250) NOT NULL DEFAULT 'Active',
  `create_datetime` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

CREATE TABLE `other_party_statement` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `party_id` int(11) NOT NULL,
  `type` varchar(250) NOT NULL,
  `payment_voucher_id` int(11) NOT NULL,
  `create_datetime` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

CREATE TABLE `payment_vouchers` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `company_id` varchar(250) NOT NULL,
  `site_id` varchar(250) NOT NULL,
  `party_type` varchar(250) NOT NULL,
  `party_id` varchar(250) NOT NULL,
  `voucher_no` varchar(250) NOT NULL,
  `amount` varchar(250) NOT NULL,
  `date` date NOT NULL,
  `payment_details` varchar(2000) DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `remark` varchar(2000) DEFAULT NULL,
  `created_by` varchar(200) NOT NULL,
  `approved_by` varchar(200) DEFAULT NULL,
  `paid_by` varchar(250) DEFAULT NULL,
  `image` varchar(2000) DEFAULT NULL,
  `payment_image` varchar(250) DEFAULT NULL,
  `status` varchar(250) NOT NULL DEFAULT 'Pending',
  `create_datetime` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

CREATE TABLE `rights` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `symbol` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

CREATE TABLE `roles` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(250) NOT NULL,
  `is_superadmin` varchar(250) NOT NULL DEFAULT 'no',
  `data_access` varchar(250) DEFAULT 'current',
  `add_duration` varchar(250) DEFAULT 'anytime',
  `view_duration` varchar(250) DEFAULT 'complete',
  `initial_entry_status` varchar(20) NOT NULL DEFAULT 'Pending',
  `entry_at_site` varchar(255) NOT NULL DEFAULT 'current',
  `visiblity_at_site` varchar(255) NOT NULL DEFAULT 'current',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

CREATE TABLE `sales_company` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `gst` varchar(255) DEFAULT NULL,
  `state` varchar(255) DEFAULT NULL,
  `state_code` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'Active',
  `create_datetime` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

CREATE TABLE `sales_dedadd` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(250) NOT NULL,
  `type` varchar(250) NOT NULL,
  `create_datetime` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

CREATE TABLE `sales_invoice` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `company_id` varchar(250) DEFAULT NULL,
  `project_id` varchar(250) DEFAULT NULL,
  `party_id` varchar(250) DEFAULT NULL,
  `financial_year` varchar(250) DEFAULT NULL,
  `invoice_no` varchar(250) DEFAULT NULL,
  `gst_rate` varchar(250) DEFAULT NULL,
  `taxable_value` varchar(250) DEFAULT NULL,
  `amount` varchar(250) DEFAULT NULL,
  `pdf` varchar(2000) DEFAULT NULL,
  `image` varchar(2000) DEFAULT NULL,
  `status` varchar(250) NOT NULL DEFAULT 'Active',
  `date` date DEFAULT NULL,
  `create_datetime` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

CREATE TABLE `sales_manage_invoice` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `invoice_id` varchar(250) NOT NULL,
  `type_id` varchar(250) NOT NULL,
  `amount` varchar(250) NOT NULL,
  `date` date NOT NULL,
  `image` varchar(500) DEFAULT NULL,
  `pdf` varchar(500) DEFAULT NULL,
  `create_datetime` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

CREATE TABLE `sales_party` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(250) DEFAULT NULL,
  `address` varchar(250) DEFAULT NULL,
  `phone` varchar(250) DEFAULT NULL,
  `gst` varchar(250) DEFAULT NULL,
  `state` varchar(250) DEFAULT NULL,
  `state_code` varchar(250) DEFAULT NULL,
  `status` varchar(250) NOT NULL DEFAULT 'Active',
  `create_datetime` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

CREATE TABLE `sales_project` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(250) DEFAULT NULL,
  `details` varchar(250) DEFAULT NULL,
  `status` varchar(250) NOT NULL DEFAULT 'Active',
  `attachment` varchar(1000) DEFAULT NULL,
  `create_datetime` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

CREATE TABLE `session` (
  `uid` varchar(500) DEFAULT NULL,
  `session_key` varchar(250) DEFAULT NULL,
  `login_time` datetime DEFAULT NULL,
  `last_activity` varchar(500) DEFAULT NULL,
  `ip_address` varchar(500) DEFAULT NULL,
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `browser` varchar(500) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

CREATE TABLE `settings` (
  `value` varchar(2000) NOT NULL,
  `name` varchar(200) NOT NULL,
  `uid` varchar(200) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

CREATE TABLE `sites` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `address` varchar(1000) NOT NULL,
  `status` varchar(250) NOT NULL DEFAULT 'Active',
  `sites_type` varchar(250) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `create_datetime` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

CREATE TABLE `sites_transaction` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `site_id` varchar(250) NOT NULL,
  `type` varchar(250) NOT NULL,
  `expense_id` varchar(200) DEFAULT NULL,
  `payment_id` int(11) DEFAULT NULL,
  `payment_voucher_id` int(11) DEFAULT NULL,
  `create_datetime` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

CREATE TABLE `site_payments` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `site_id` varchar(250) NOT NULL,
  `amount` varchar(250) NOT NULL,
  `remark` varchar(2000) NOT NULL,
  `date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

CREATE TABLE `tasks` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `site_id` int(11) NOT NULL,
  `parent_task_id` int(11) DEFAULT NULL,
  `task_type` enum('HEADING','SUBHEADING','TASK') NOT NULL DEFAULT 'TASK',
  `title` varchar(500) NOT NULL,
  `description` text DEFAULT NULL,
  `total_units` varchar(250) NOT NULL,
  `unit_type` varchar(250) NOT NULL,
  `priority` enum('LOW','MEDIUM','HIGH','CRITICAL') DEFAULT 'LOW',
  `status` enum('Awaiting','Progress','Pending','Completed') NOT NULL DEFAULT 'Awaiting',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

CREATE TABLE `task_progress` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `completed_units` varchar(250) NOT NULL,
  `date` varchar(250) NOT NULL,
  `updated_by` int(11) NOT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `remark` varchar(2000) DEFAULT NULL,
  `location` varchar(2000) DEFAULT NULL,
  `image` varchar(2000) DEFAULT NULL,
  `approved_by` int(11) NOT NULL,
  `approved_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

CREATE TABLE `units` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

CREATE TABLE `users` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(250) NOT NULL,
  `username` varchar(250) NOT NULL,
  `pass` varchar(250) NOT NULL,
  `subscription_plan_id` int(11) DEFAULT NULL,
  `site_id` varchar(200) NOT NULL,
  `role_id` int(11) NOT NULL,
  `view_duration` varchar(50) DEFAULT NULL,
  `add_duration` varchar(50) DEFAULT NULL,
  `pan_no` varchar(50) DEFAULT NULL,
  `status` varchar(200) NOT NULL DEFAULT 'Active',
  `image` varchar(2000) NOT NULL DEFAULT 'images/noprofile.jpg',
  `contact_no` varchar(250) DEFAULT NULL,
  `mobile_only` varchar(250) NOT NULL DEFAULT 'yes',
  `fcm_id` varchar(250) DEFAULT NULL,
  `create_datetime` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

CREATE TABLE `role_permission` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `module_id` int(11) NOT NULL,
  `can_view` int(11) NOT NULL DEFAULT 0,
  `can_add` int(11) NOT NULL DEFAULT 0,
  `can_edit` int(11) NOT NULL DEFAULT 0,
  `can_certify` int(11) NOT NULL DEFAULT 0,
  `can_pay` int(11) NOT NULL DEFAULT 0,
  `can_delete` int(11) NOT NULL DEFAULT 0,
  `can_report` int(11) NOT NULL DEFAULT 0,
  `create_datetime` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

CREATE TABLE `user_permission` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `subscription_plan_id` int(11) DEFAULT NULL,
  `module_id` int(11) NOT NULL,
  `can_view` int(11) NOT NULL DEFAULT 0,
  `can_add` int(11) NOT NULL DEFAULT 0,
  `can_edit` int(11) NOT NULL DEFAULT 0,
  `can_certify` int(11) NOT NULL DEFAULT 0,
  `can_pay` int(11) NOT NULL DEFAULT 0,
  `can_delete` int(11) NOT NULL DEFAULT 0,
  `can_report` int(11) NOT NULL DEFAULT 0,
  `create_datetime` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

CREATE TABLE `user_sites` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `site_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- Indexes and AUTO_INCREMENT

ALTER TABLE `role_permission`

ALTER TABLE `role_permission`

ALTER TABLE `activity`

ALTER TABLE `activity`

ALTER TABLE `assets`
  ADD UNIQUE KEY `expense_id` (`expense_id`);

ALTER TABLE `assets`

ALTER TABLE `assets_expense_head`
  ADD UNIQUE KEY `head_id` (`head_id`);

ALTER TABLE `assets_expense_head`

ALTER TABLE `asset_head`
  ADD UNIQUE KEY `name` (`name`);

ALTER TABLE `asset_head`

ALTER TABLE `asset_transaction`

ALTER TABLE `asset_transaction`

ALTER TABLE `bills_party`

ALTER TABLE `bills_party`

ALTER TABLE `bills_rate`

ALTER TABLE `bills_rate`

ALTER TABLE `bills_work`

ALTER TABLE `bills_work`

ALTER TABLE `bill_party_payments`

ALTER TABLE `bill_party_payments`

ALTER TABLE `bill_party_statement`
  ADD UNIQUE KEY `expense_id` (`expense_id`),
  ADD UNIQUE KEY `bill_no` (`bill_no`),
  ADD UNIQUE KEY `payment_id` (`payment_id`),
  ADD UNIQUE KEY `payment_voucher_id` (`payment_voucher_id`);

ALTER TABLE `bill_party_statement`

ALTER TABLE `contact`

ALTER TABLE `contact`

ALTER TABLE `contact_profile`

ALTER TABLE `contact_profile`

ALTER TABLE `data_time`

ALTER TABLE `data_time`

ALTER TABLE `doc_head`

ALTER TABLE `doc_head`

ALTER TABLE `doc_head_option`

ALTER TABLE `doc_head_option`

ALTER TABLE `doc_meta`

ALTER TABLE `doc_meta`

ALTER TABLE `doc_upload`

ALTER TABLE `doc_upload`

ALTER TABLE `expenses`

ALTER TABLE `expenses`

ALTER TABLE `expense_head`
  ADD UNIQUE KEY `name` (`name`);

ALTER TABLE `expense_head`

ALTER TABLE `expense_party`

ALTER TABLE `expense_party`

ALTER TABLE `machinery_details`

ALTER TABLE `machinery_details`

ALTER TABLE `machinery_documents`

ALTER TABLE `machinery_documents`

ALTER TABLE `machinery_expense_head`

ALTER TABLE `machinery_expense_head`

ALTER TABLE `machinery_head`

ALTER TABLE `machinery_head`

ALTER TABLE `machinery_services`

ALTER TABLE `machinery_services`

ALTER TABLE `machinery_transaction`

ALTER TABLE `machinery_transaction`

ALTER TABLE `materials`

ALTER TABLE `materials`

ALTER TABLE `material_consumption`

ALTER TABLE `material_consumption`

ALTER TABLE `material_conversion_rules`

ALTER TABLE `material_conversion_rules`

ALTER TABLE `material_entry`

ALTER TABLE `material_entry`

ALTER TABLE `material_reconsilation_data`

ALTER TABLE `material_reconsilation_data`

ALTER TABLE `material_reconsilation_record`

ALTER TABLE `material_reconsilation_record`

ALTER TABLE `material_site_transfers`

ALTER TABLE `material_site_transfers`

ALTER TABLE `material_stock_record`

ALTER TABLE `material_stock_record`

ALTER TABLE `material_stock_transactions`

ALTER TABLE `material_stock_transactions`

ALTER TABLE `material_supplier`

ALTER TABLE `material_supplier`

ALTER TABLE `material_supplier_statement`

ALTER TABLE `material_supplier_statement`

ALTER TABLE `material_units_conversion_record`

ALTER TABLE `material_units_conversion_record`

ALTER TABLE `material_wastage`

ALTER TABLE `material_wastage`

ALTER TABLE `new_bills_item_entry`

ALTER TABLE `new_bills_item_entry`

ALTER TABLE `new_bill_entry`
  ADD UNIQUE KEY `bill_no` (`bill_no`);

ALTER TABLE `new_bill_entry`

ALTER TABLE `other_parties`
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `panno` (`panno`);

ALTER TABLE `other_parties`

ALTER TABLE `other_party_statement`

ALTER TABLE `other_party_statement`

ALTER TABLE `payment_vouchers`
  ADD UNIQUE KEY `voucher_no` (`voucher_no`);

ALTER TABLE `payment_vouchers`

ALTER TABLE `rights`

ALTER TABLE `rights`

ALTER TABLE `roles`
  ADD UNIQUE KEY `name` (`name`);

ALTER TABLE `roles`

ALTER TABLE `sales_company`
  ADD UNIQUE KEY `gst` (`gst`),
  ADD UNIQUE KEY `name` (`name`);

ALTER TABLE `sales_company`

ALTER TABLE `sales_dedadd`
  ADD UNIQUE KEY `name` (`name`);

ALTER TABLE `sales_dedadd`

ALTER TABLE `sales_invoice`
  ADD UNIQUE KEY `invoice_no` (`invoice_no`);

ALTER TABLE `sales_invoice`

ALTER TABLE `sales_manage_invoice`

ALTER TABLE `sales_manage_invoice`

ALTER TABLE `sales_party`
  ADD UNIQUE KEY `name` (`name`);

ALTER TABLE `sales_party`

ALTER TABLE `sales_project`
  ADD UNIQUE KEY `name` (`name`);

ALTER TABLE `sales_project`

ALTER TABLE `session`

ALTER TABLE `session`

ALTER TABLE `settings`
  ADD UNIQUE KEY `name` (`name`);

ALTER TABLE `settings`

ALTER TABLE `sites`

ALTER TABLE `sites`

ALTER TABLE `sites_transaction`
  ADD UNIQUE KEY `expense_id` (`expense_id`),
  ADD UNIQUE KEY `payment_id` (`payment_id`);

ALTER TABLE `sites_transaction`

ALTER TABLE `site_payments`

ALTER TABLE `site_payments`

ALTER TABLE `tasks`

ALTER TABLE `tasks`

ALTER TABLE `task_progress`

ALTER TABLE `task_progress`

ALTER TABLE `units`

ALTER TABLE `units`

ALTER TABLE `users`

ALTER TABLE `users`

ALTER TABLE `user_permission`

ALTER TABLE `user_permission`

ALTER TABLE `user_sites`

ALTER TABLE `user_sites`


-- Default Data

INSERT INTO `sites` (`id`, `name`, `address`, `status`) VALUES (45, 'Head Office', 'N/A', 'Active');

INSERT INTO `settings` (`name`, `value`, `uid`) VALUES
('menutheme', 'menu_dark', ''),
('theme', 'green', ''),
('primary_color', '#6f42c1', ''),
('secondry_color', '#6f42c1', ''),
('gradient_start', '#6f42c1', ''),
('gradient_end', '#6f42c1', ''),
('currency', 'INR', ''),
('upload_src', 'local', ''),
('bill_sequence', 'BILL/2024-25/', ''),
('payment_voucher_sequence', 'VCH/2024-25/', ''),
('expense_upload_src', 'Both', ''),
('material_first_upload_src', 'Both', ''),
('material_second_upload_src', 'Both', ''),
('machinery_doc_upload_src', 'Both', ''),
('machinery_service_upload_src', 'Both', ''),
('document_upload_src', 'Both', '');

INSERT INTO `roles` (`id`, `name`, `is_superadmin`, `visibility_at_site`, `data_access`, `view_duration`, `add_duration`) VALUES (1, 'Super Admin', 1, 1, 'all', 'all', 'all');
