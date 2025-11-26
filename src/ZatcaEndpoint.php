<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi;

class ZatcaEndpoint
{
    public const REPORTING = '/invoices/reporting/single';

    public const CLEARANCE = '/invoices/clearance/single';

    public const COMPLIANCE_INVOICE = '/compliance/invoices';

    public const COMPLIANCE_CERTIFICATE = '/compliance';

    public const PRODUCTION_CERTIFICATE = '/production/csids';
}
