<?php

namespace ErnestDefoe\Garage;

final class Settings
{
    public const VIEW = 'ernestdefoe-garage.view';

    public const CREATE = 'ernestdefoe-garage.create';

    public const LIKE = 'ernestdefoe-garage.like';

    public const COMMENT = 'ernestdefoe-garage.comment';

    public const MODERATE = 'ernestdefoe-garage.moderate';

    /** Show "Garage" in the forum's sidebar. */
    public const NAV = 'ernestdefoe-garage.nav';

    /** Photos go to FoF Upload's storage when it is enabled (and the member may upload there). */
    public const USE_FOF_UPLOAD = 'ernestdefoe-garage.use_fof_upload';

    /** Largest photo a member may upload to Garage's own storage, in KB. */
    public const MAX_UPLOAD_KB = 'ernestdefoe-garage.max_upload_kb';

    /** Put in front of money amounts: "$", "€", "£ "… */
    public const CURRENCY = 'ernestdefoe-garage.currency';

}
