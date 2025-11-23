<?php

namespace Sevaske\ZatcaApi;

class ZatcaEndpointEnum: string
{
    public const REPORTING = '/invoices/reporting/single';

    public const CLEARANCE = '/invoices/clearance/single';

    public const COMPLIANCE_INVOICE = '/compliance/invoices';

    public const COMPLIANCE_CERTIFICATE = '/compliance';

    public const PRODUCTION_CERTIFICATE = '/production/csids';
}
