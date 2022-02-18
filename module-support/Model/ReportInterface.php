<?php

/**
 * 
 */
namespace Altayer\Support\Model;

/**
 * Interface ReportInterface
 * @package Altayer\Support\Model
 */
interface ReportInterface
{
    const REPORT_ID = 'report_id';
    const REPORT_NAME = 'report_name';
    const REPORT_CREATED_USER = 'report_created_by';
    const REPORT_UPDATED_USER = 'report_modified_by';
    const REPORT_SQL = 'report_sql';
    const CREATION_TIME = 'created_at';
    const UPDATE_TIME = 'updated_at';
}