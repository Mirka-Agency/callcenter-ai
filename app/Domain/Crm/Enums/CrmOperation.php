<?php

namespace App\Domain\Crm\Enums;

enum CrmOperation: string
{
    case TestConnection = 'test_connection';
    case CreateLead = 'create_lead';
    case UpdateLead = 'update_lead';
    case GetLead = 'get_lead';
    case CreateContact = 'create_contact';
    case CreateTask = 'create_task';
    case SyncData = 'sync_data';
    case ListPipelines = 'list_pipelines';
    case ListUsers = 'list_users';
    case SyncCallIntelligence = 'sync_call_intelligence';

    public function label(): string
    {
        return match ($this) {
            self::TestConnection => 'آزمایش اتصال',
            self::CreateLead => 'ایجاد سرنخ',
            self::UpdateLead => 'به‌روزرسانی سرنخ',
            self::GetLead => 'دریافت سرنخ',
            self::CreateContact => 'ایجاد مخاطب',
            self::CreateTask => 'ایجاد وظیفه',
            self::SyncData => 'همگام‌سازی داده',
            self::ListPipelines => 'فهرست کاریزها',
            self::ListUsers => 'فهرست کاربران CRM',
            self::SyncCallIntelligence => 'همگام‌سازی هوش تماس',
        };
    }
}
