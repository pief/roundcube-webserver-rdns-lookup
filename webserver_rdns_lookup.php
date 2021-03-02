<?php

/**
 * Webserver rDNS lookup
 *
 * Plugin to augment the configured product name with the actual webserver's hostname
 * (without the domain part) determined through a reverse DNS lookup of a configurable
 * interface's IPv4 address.
 *
 * @license GNU GPLv3+
 * @author Pieter Hollants
 * @website https://github.com/pief/roundcube/webserver_rdns_lookup
 */
class webserver_rdns_lookup extends rcube_plugin
{
  /**
   * Plugin initialization.
   */
  function init()
  {
    $this->load_config();

    /* Get host's primary IP address... */
    $ipaddr = $this->get_interface_ipaddr();
    if ($ipaddr == NULL)
    {
      rcmail::raise_error(array(
        'file' => __FILE__,
        'line' => __LINE__,
        'message' => "Could not determine server's primary IP address"
      ), true, false);
      return;
    }

    /* ...and reverse-lookup the FQDN for it. */
    $fqdn = $this->reverse_dns_lookup($ipaddr);
    if ($fqdn == NULL)
    {
      rcmail::raise_error(array(
        'file' => __FILE__,
        'line' => __LINE__,
        'message' => "Could not reverse-lookup server's IP address"
      ), true, false);
      return;
    }
    $hostname = explode(".", $fqdn)[0];

    /* ...and append the hostname to the product_name to inform the user
       which server she's actually using. */
    $rcmail = rcmail::get_instance();
    $product_name = $rcmail->config->get("product_name");
    $product_name .= " ($hostname)";
    $rcmail->config->set("product_name", $product_name);
  }

  /**
   * Returns the first IPv4 address configured on the configured network interface.
   */
  function get_interface_ipaddr()
  {
    $rcmail = rcmail::get_instance();

    $ifname = $rcmail->config->get('webserver_rdns_lookup_interface', 'eth0');

    $output = exec("ip -j addr show $ifname");
    if ($output == NULL)
      return NULL;

    $ifinfo = json_decode($output);
    foreach ($ifinfo[0]->addr_info as $addr) {
      if ($addr->family == "inet")
        return $addr->local;
    };

    return NULL;
  }

  /**
   * Performs a reverse DNS lookup for the specified IPv4 address.
   */
  function reverse_dns_lookup($ipaddr)
  {
    $parts = explode(".", $ipaddr);
    $result = dns_get_record(sprintf("%s.%s.%d.%s.in-addr.arpa", $parts[3], $parts[2], $parts[1], $parts[0]), DNS_PTR);
    if (!$result)
      return NULL;
    return $result[0]["target"];
  }
}
