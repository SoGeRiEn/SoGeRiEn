<?php
declare(strict_types=1);

final class API
{
    use SogerienClassHelp;

    private ?APICyberyozh $Cyberyozh = null;
    private ?APIInfaticaIo $InfaticaIo = null;
    private ?APIInfaticaIo_mobile $InfaticaIo_mobile = null;
    private ?APIInfaticaIo_residential $InfaticaIo_residential = null;
    private ?APIInfaticaIo_isp $InfaticaIo_isp = null;
    private ?APIInfaticaIo_dc $InfaticaIo_dc = null;
    private ?APIInfaticaIo_scraper $InfaticaIo_scraper = null;
    private ?APIInfaticaIo_catalog $InfaticaIo_catalog = null;
    private ?APIProxysmartorg $Proxysmartorg = null;
    private ?APIiproxyonline $Iproxyonline = null;
    private ?APIhypeproxyio $Hypeproxyio = null;
    private ?APIdataimpulsecom $Dataimpulsecom = null;
    private ?APIStripe $Stripe = null;
    private ?APIPostgresql $Postgresql = null;
    private ?APIGoogleOAuth $GoogleOAuth = null;

    public function Cyberyozh(): APICyberyozh   { return $this->Cyberyozh ??= new APICyberyozh(); }
    public function InfaticaIo(): APIInfaticaIo { return $this->InfaticaIo ??= new APIInfaticaIo(); }
    public function InfaticaIo_mobile(): APIInfaticaIo_mobile { return $this->InfaticaIo_mobile ??= $this->InfaticaIo()->Mobile(); }
    public function InfaticaIo_residential(): APIInfaticaIo_residential { return $this->InfaticaIo_residential ??= $this->InfaticaIo()->Residential(); }
    public function InfaticaIo_isp(): APIInfaticaIo_isp { return $this->InfaticaIo_isp ??= $this->InfaticaIo()->Isp(); }
    public function InfaticaIo_dc(): APIInfaticaIo_dc { return $this->InfaticaIo_dc ??= $this->InfaticaIo()->Dc(); }
    public function InfaticaIo_scraper(): APIInfaticaIo_scraper { return $this->InfaticaIo_scraper ??= $this->InfaticaIo()->Scraper(); }
    public function InfaticaIo_catalog(): APIInfaticaIo_catalog { return $this->InfaticaIo_catalog ??= $this->InfaticaIo()->Catalog(); }
    public function Proxysmartorg(): APIProxysmartorg { return $this->Proxysmartorg ??= new APIProxysmartorg(); }
    public function Iproxyonline(): APIiproxyonline { return $this->Iproxyonline ??= new APIiproxyonline(); }
    public function Hypeproxyio(): APIhypeproxyio { return $this->Hypeproxyio ??= new APIhypeproxyio(); }
    public function Dataimpulsecom(): APIdataimpulsecom { return $this->Dataimpulsecom ??= new APIdataimpulsecom(); }
    public function Stripe(): APIStripe { return $this->Stripe ??= new APIStripe(); }
    public function Postgresql(): APIPostgresql { return $this->Postgresql ??= new APIPostgresql(); }
    public function GoogleOAuth(): APIGoogleOAuth { return $this->GoogleOAuth ??= new APIGoogleOAuth(); }
}
