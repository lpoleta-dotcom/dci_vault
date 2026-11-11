<?php
if (session_status() === PHP_SESSION_NONE) {
    session_name('DCI_CUSTOMER_SESSION_V2');
    session_start();
}
