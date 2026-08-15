<?php

namespace App\Enums;

enum UserRole: string
{
    case STUDENT = 'student';
    case SRO_OFFICER = 'sro_officer';
    case ISC_OFFICER = 'isc_officer';
    case SSC_OFFICER = 'ssc_officer';
    case SRO_HEAD = 'sro_head';
    case INSTITUTE_HEAD = 'institute_head';
    case SSC_HEAD = 'ssc_head';
    case SUPER_ADMIN = 'super_admin';

    /**
     * Roles allowed to sign into the web admin portal.
     */
    public static function adminPortalRoles(): array
    {
        return [
            self::SUPER_ADMIN,
            self::SSC_HEAD,
            self::INSTITUTE_HEAD,
            self::SRO_HEAD,
        ];
    }

    /**
     * Roles that head an organization (portal monitoring + officer management).
     */
    public static function headRoles(): array
    {
        return [
            self::SSC_HEAD,
            self::INSTITUTE_HEAD,
            self::SRO_HEAD,
        ];
    }

    /**
     * Junior officer roles (no portal access, no head duties).
     */
    public static function staffRoles(): array
    {
        return [
            self::SSC_OFFICER,
            self::ISC_OFFICER,
            self::SRO_OFFICER,
        ];
    }

    /**
     * Adviser roles (ISC + SRO heads) displayed on the read-only Advisers page.
     */
    public static function adviserRoles(): array
    {
        return [
            self::INSTITUTE_HEAD,
            self::SRO_HEAD,
        ];
    }

    /**
     * Roles granted officer-style capabilities in an organization.
     */
    public static function officerRoles(): array
    {
        return [
            self::SSC_HEAD,
            self::INSTITUTE_HEAD,
            self::SRO_HEAD,
            self::SSC_OFFICER,
            self::ISC_OFFICER,
            self::SRO_OFFICER,
        ];
    }
}
