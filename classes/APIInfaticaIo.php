<?php
declare(strict_types=1);

final class APIInfaticaIo
{
    private APIInfaticaIo_transport $Transport;
    private ?APIInfaticaIo_mobile $Mobile = null;
    private ?APIInfaticaIo_residential $Residential = null;
    private ?APIInfaticaIo_isp $Isp = null;
    private ?APIInfaticaIo_dc $Dc = null;
    private ?APIInfaticaIo_scraper $Scraper = null;
    private ?APIInfaticaIo_catalog $Catalog = null;

    public function __construct(?APIInfaticaIo_transport $transport = null)
    {
        $this->Transport = $transport ?? new APIInfaticaIo_transport();
    }

    public function Transport(): APIInfaticaIo_transport
    {
        return $this->Transport;
    }

    public function Mobile(): APIInfaticaIo_mobile
    {
        return $this->Mobile ??= new APIInfaticaIo_mobile($this->Transport);
    }

    public function Residential(): APIInfaticaIo_residential
    {
        return $this->Residential ??= new APIInfaticaIo_residential($this->Transport);
    }

    public function Isp(): APIInfaticaIo_isp
    {
        return $this->Isp ??= new APIInfaticaIo_isp($this->Transport);
    }

    public function Dc(): APIInfaticaIo_dc
    {
        return $this->Dc ??= new APIInfaticaIo_dc($this->Transport);
    }

    public function Scraper(): APIInfaticaIo_scraper
    {
        return $this->Scraper ??= new APIInfaticaIo_scraper($this->Transport);
    }

    public function Catalog(): APIInfaticaIo_catalog
    {
        return $this->Catalog ??= new APIInfaticaIo_catalog($this->Transport);
    }

    public function set_api_key(string $api_key): void
    {
        $this->Transport->set_api_key($api_key);
    }

    public function set_base_url(string $base_url): void
    {
        $this->Transport->set_base_url($base_url);
    }

    public function set_client_base_url(string $base_url): void
    {
        $this->Transport->set_client_base_url($base_url);
    }

    public function set_scraper_base_url(string $base_url): void
    {
        $this->Transport->set_scraper_base_url($base_url);
    }

    public function set_client_auth(string $email, string $password): void
    {
        $this->Transport->set_client_auth($email, $password);
    }

    public function set_residential_api_key(string $api_key): void
    {
        $this->Transport->set_residential_api_key($api_key);
    }

    public function set_mobile_api_key(string $api_key): void
    {
        $this->Transport->set_mobile_api_key($api_key);
    }

    public function set_isp_api_key(string $api_key): void
    {
        $this->Transport->set_isp_api_key($api_key);
    }

    public function set_dc_api_key(string $api_key): void
    {
        $this->Transport->set_dc_api_key($api_key);
    }

    public function set_scraper_api_key(string $api_key): void
    {
        $this->Transport->set_scraper_api_key($api_key);
    }

    /**
     * @param array<string,array<int,float>> $pricing
     */
    public function set_pricing(array $pricing): void
    {
        $this->Catalog()->set_pricing($pricing);
    }
}
