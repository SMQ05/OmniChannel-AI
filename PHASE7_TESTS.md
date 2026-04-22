# Phase 7 Feature Tests

This document describes the test suite for Phase 7 - Admin Control Plane.

## Test Files

### 1. AdminOnboardingControlPlaneTest.php
Tests for the Onboarding and Launch Readiness control plane.

**Test Cases:**
- `test_super_admin_can_view_onboarding_index` - Verify index page loads
- `test_super_admin_can_view_business_onboarding_details` - Verify business details page
- `test_super_admin_can_mark_onboarding_as_complete` - Verify onboarding completion
- `test_super_admin_can_approve_business_launch` - Verify launch approval with audit logging
- `test_super_admin_can_reset_onboarding_state` - Verify state reset functionality
- `test_super_admin_can_refresh_launch_readiness` - Verify readiness refresh
- `test_non_super_admin_cannot_access_onboarding_controls` - Verify authorization

### 2. AdminSupportWorkspaceTest.php
Tests for the Support Actions workspace with auditable actions.

**Test Cases:**
- `test_super_admin_can_view_support_index` - Verify support workspace list
- `test_super_admin_can_view_business_support_workspace` - Verify business details page
- `test_super_admin_can_add_support_note` - Verify private note creation with audit logging
- `test_super_admin_can_add_public_support_note` - Verify public note creation
- `test_super_admin_can_replay_last_inbound_message` - Verify webhook replay
- `test_super_admin_can_rerun_billing_cycle` - Verify billing rerun with audit logging
- `test_super_admin_can_refresh_launch_readiness` - Verify launch readiness refresh
- `test_super_admin_can_export_audit_log` - Verify CSV export
- `test_non_super_admin_cannot_access_support_workspace` - Verify authorization

### 3. AdminCredentialsMetadataTest.php
Tests for Credential Metadata management (metadata-only, never secrets).

**Test Cases:**
- `test_super_admin_can_view_credentials_index` - Verify index page
- `test_super_admin_can_view_credential_details` - Verify credential details with verification info
- `test_super_admin_can_record_credential_rotation` - Verify rotation recording
- `test_super_admin_can_update_credential_settings` - Verify settings update
- `test_super_admin_can_record_credential_verification` - Verify verification recording
- `test_super_admin_can_deactivate_credential` - Verify credential deactivation
- `test_super_admin_can_create_credential_metadata_for_messaging_connection` - Verify creation
- `test_non_super_admin_cannot_access_credentials_metadata` - Verify authorization

### 4. AdminAiPolicyControlTest.php
Tests for AI Policy management (rate limits, guardrails, feature flags).

**Test Cases:**
- `test_super_admin_can_view_ai_policy_index` - Verify AI policy dashboard
- `test_super_admin_can_view_business_ai_policy` - Verify business AI policy view
- `test_super_admin_can_update_ai_policy` - Verify policy update with audit logging
- `test_super_admin_can_reset_ai_policy_to_defaults` - Verify defaults reset
- `test_non_super_admin_cannot_access_ai_policy` - Verify authorization

### 5. AdminIncidentBannersTest.php
Tests for Incident Banner management (platform and business scoped).

**Test Cases:**
- `test_super_admin_can_view_incident_banners_index` - Verify index page
- `test_super_admin_can_create_incident_banner` - Verify create form
- `test_super_admin_can_store_incident_banner` - Verify banner creation
- `test_super_admin_can_create_platform_wide_incident` - Verify platform-wide scope
- `test_super_admin_can_edit_incident_banner` - Verify edit form
- `test_super_admin_can_update_incident_banner` - Verify banner update
- `test_super_admin_can_publish_incident` - Verify publish with audit logging
- `test_super_admin_can_archive_incident` - Verify archive functionality
- `test_super_admin_can_resolve_incident` - Verify resolve with audit logging
- `test_super_admin_can_delete_incident` - Verify delete functionality
- `test_super_admin_can_view_active_business_incidents` - Verify business-scoped incidents
- `test_non_super_admin_cannot_access_incident_banners` - Verify authorization

### 6. AdminAuditLogTest.php
Tests for Audit Log Explorer.

**Test Cases:**
- `test_super_admin_can_view_audit_logs_index` - Verify index page
- `test_super_admin_can_view_single_audit_log_entry` - Verify details page
- `test_super_admin_can_search_audit_logs_by_request_id` - Verify request ID search
- `test_super_admin_can_export_audit_logs` - Verify CSV export
- `test_super_admin_can_view_audit_logs_for_business` - Verify business-scoped logs
- `test_super_admin_can_view_recent_audit_logs` - Verify recent actions view
- `test_audit_log_captures_admin_actions` - Verify audit logging for privileged actions
- `test_non_super_admin_cannot_access_audit_logs` - Verify authorization

## Authorization Tests

All test files include authorization tests ensuring:
- `super_admin` role can access all Phase 7 controls
- `support_admin` role has limited access (where applicable)
- `business_owner`, `manager`, `receptionist`, and `staff` roles are denied access
- Audit logging captures all privileged actions

## Running Tests

```bash
# Run all Phase 7 tests
php artisan test --filter=Admin

# Run a specific test file
php artisan test tests/Feature/AdminOnboardingControlPlaneTest.php

# Run a specific test
php artisan test --filter=test_super_admin_can_approve_business_launch

# Run with coverage
php artisan test --coverage
```

## Database Migrations Required

The following migrations must be applied before running tests:

1. `2026_04_19_050000_create_business_launch_states_table.php`
2. `2026_04_19_050001_create_incident_banners_table.php`
3. `2026_04_19_050002_create_credential_metadata_table.php`
4. `2026_04_19_050003_add_launch_readiness_columns_to_business_subscriptions.php`
5. `2026_04_19_050004_add_ai_policy_columns_to_businesses_table.php`

## Architecture Notes

### Separation of Concerns

1. **BusinessLaunchState** - Tracks admin workflow state only, NOT runtime/billing truth
2. **LaunchReadinessService** - Derived-only, aggregates from other services (no state)
3. **CredentialMetadata** - Metadata workspace only (rotation tracking, verification status)
4. **Audit Logging** - Captures all privileged admin actions

### Permission Model

- All routes protected by `RequireSuperAdmin` middleware
- Actions that modify state are logged in `audit_logs` table
- Audit logs include: actor_id, business_id, action, payload, request_id, ip_address, user_agent

### Testing Strategy

- Feature tests using `RefreshDatabase` trait for isolation
- Each test verifies both positive (allowed) and negative (denied) cases
- Audit log verification ensures privileged actions are captured
