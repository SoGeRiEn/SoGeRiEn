<?php
declare(strict_types=1);

final class API
{
    private ?APICyberyozh $Cyberyozh = null;
    private ?APIInfaticaIo $InfaticaIo = null;
    private ?APIProxysmartorg $Proxysmartorg = null;
    private ?APIiproxyonline $Iproxyonline = null;
    private ?APIhypeproxyio $Hypeproxyio = null;
    private ?APIdataimpulsecom $Dataimpulsecom = null;
    private ?APIStripe $Stripe = null;
    private ?APIPostgresql $Postgresql = null;

    public function Cyberyozh(): APICyberyozh   { return $this->Cyberyozh ??= new APICyberyozh(); }
    public function InfaticaIo(): APIInfaticaIo { return $this->InfaticaIo ??= new APIInfaticaIo(); }
    public function Proxysmartorg(): APIProxysmartorg { return $this->Proxysmartorg ??= new APIProxysmartorg(); }
    public function Iproxyonline(): APIiproxyonline { return $this->Iproxyonline ??= new APIiproxyonline(); }
    public function Hypeproxyio(): APIhypeproxyio { return $this->Hypeproxyio ??= new APIhypeproxyio(); }
    public function Dataimpulsecom(): APIdataimpulsecom { return $this->Dataimpulsecom ??= new APIdataimpulsecom(); }
    public function Stripe(): APIStripe { return $this->Stripe ??= new APIStripe(); }
    public function Postgresql(): APIPostgresql { return $this->Postgresql ??= new APIPostgresql(); }
}
