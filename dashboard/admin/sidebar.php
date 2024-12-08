<?php
include_once '../../config/settings-configuration.php';
include_once 'header.php';


$config = new SystemConfig();

class SideBar
{
    private $config;
    private $currentPage;

    public function __construct($config, $currentPage)
    {
        $this->config = $config;
        $this->currentPage = $currentPage;
    }

    private function isActive($pageName)
    {
        return $this->currentPage === $pageName ? 'active' : '';
    }

    public function getSideBar()
    {
        return '
        <section id="sidebar">
            <a href="" class="brand">
                <img src="../../src/img/logo1.png" alt="logo">
                <span class="text">ADUTECT<br>
                    <p>Smart Smoke Detection</p>
                </span>
            </a>
            <ul class="side-menu top">
                <li class="' . $this->isActive('index') . '">
                    <a href="./">
                        <i class="bx bxs-dashboard"></i>
                        <span class="text">Dashboard</span>
                    </a>
                </li>
                <li class="' . $this->isActive('reports') . '">
                <a href="reports">
                    <i class="bx bxs-report"></i>
                    <span class="text">Reports</span>
                </a>
            </li>
            </li>
            </ul>
            <ul class="side-menu top">
                <li class="' . $this->isActive('settings') . '">
                    <a href="settings">
                        <i class="bx bxs-cog"></i>
                        <span class="text">Settings</span>
                    </a>
                </li>
                <li class="' . $this->isActive('audit-trail') . '">
                    <a href="audit-trail">
                        <i class="bx bxl-blogger"></i>
                        <span class="text">Audit Trail</span>
                    </a>
                </li>
                <li>
                    <a href="authentication/admin-signout" class="btn-signout">
                        <i class="bx bxs-log-out-circle"></i>
                        <span class="text">Signout</span>
                    </a>
                </li>
            </ul>
        </section>';
    }
}
