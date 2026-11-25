<?php

use RSE\Delivra\Facades\InApp;

// loaded when delivra.in_app.routes.enabled is true. Apps that need other groups call InApp::routes() themselves.
InApp::routes();
