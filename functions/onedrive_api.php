<?php
function get_authorize_url() {
    global $appId, $redirectUri, $scopes, $tenantId; // $appId, $redirectUri và $scopes được định nghĩa trước đó
  
    $authUrl = "https://login.microsoftonline.com/" . $tenantId . "/oauth2/v2.0/authorize?client_id=" . $appId . 
               "&response_type=code&redirect_uri=" . urlencode($redirectUri) . 
               "&scope=" . urlencode(implode(' ', $scopes)) .
               "&response_mode=query"; // response_mode=query để tránh sử dụng fragment URL.
  
    return $authUrl;
  }