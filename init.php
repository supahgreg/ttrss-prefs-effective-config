<?php
class Prefs_Effective_Config extends Plugin {

  private $host;
  private const CONFIG_KEYS_TO_MASK = ['DB_PASS'];
  private const PARAM_TYPE_TO_NAME = [
    Config::T_BOOL => 'boolean',
    Config::T_STRING => 'string',
    Config::T_INT => 'integer',
  ];

  function about() {
    return [
      0.6, // version
      'Shows your effective tt-rss config @ Preferences --> System', // description
      'wn', // author
      false, // is system
      'https://github.com/supahgreg/ttrss-prefs-effective-config/', // more info URL
    ];
  }

  function api_version() {
    return 2;
  }

  function init($host) {
    $this->host = $host;
    $host->add_hook($host::HOOK_PREFS_TAB, $this);
  }

  function hook_prefs_tab($tab) {
    if ($tab != 'prefSystem' || !self::is_admin()) {
      return;
    }
?>
    <div dojoType='dijit.layout.AccordionPane' title='<i class="material-icons">subject</i> <?= __('Effective Config') ?>'>
          <script type='dojo/method' event='onSelected' args='evt'>
            if (!this.domNode.querySelector('.loading')) {
              return;
            }

            window.setTimeout(() => {
              xhr.json('backend.php', {op: 'pluginhandler', plugin: 'prefs_effective_config', method: 'get_effective_config'}, (reply) => {
                this.attr('content', `
                <style type='text/css'>
                  #config-items-list { text-align: left; border-spacing: 0; }

                  #config-items-list .redacted { opacity: 0.5; }
                  #config-items-list .envvar_prefix { opacity: 0.4; }

                  #config-items-list th { border-bottom: 1px solid #000; }
                  #config-items-list tbody tr:hover { background: #eee; }
                  #config-items-list tbody td { padding: 5px; }
                  #config-items-list tbody td.green { background-color: rgba(0, 255, 0, 0.1); }
                  #config-items-list tbody td.red { background-color: rgba(255, 0, 0, 0.1); }
                  #config-items-list tbody td.gray { background-color: rgba(128, 128, 128, 0.1); }
                  #config-items-list td:not(:last-child) { border-right: 1px solid #ccc; }
                </style>

                <table id='config-items-list'>
                  <thead>
                    <tr>
                      <th>${__('Name')}</th>
                      <th>${__('Effective Value')}</th>
                      <th>${__('Environment Variable Value')}</th>
                      <th>${__('Default Value')}</th>
                      <th>${__('Type Hint')}</th>
                    </tr>
                  </thead>
                  <tbody>
                  ${
                    reply.params.map(param => `
                      <tr>
                        <td><span class="envvar_prefix">${reply.envvar_prefix}</span>${param.name}</td>
                        ${param.should_redact ? `<td class='redacted gray'>redacted</td>` :
                            `<td class='${[param.env_val, param.default_val].includes(param.effective_val) ? 'green' : 'red'}'>${param.effective_val}</td>`}
                        ${param.should_redact ? `<td class='redacted gray'>${param.env_val}</td>` :
                            `<td class='${param.effective_val == param.env_val ? 'green' : 'red'}'>${param.env_val}</td>`}
                        <td class='${param.should_redact ? 'gray' :
                            param.effective_val == param.default_val ? 'green' : 'red'}'>${param.default_val}</td>
                        <td>${param.type_hint}</td>
                      </tr>
                    `).join('')
                  }
                  </tbody>
                </table>
                `);
              });
            }, 200);
          </script>
          <span class='loading'><?= __('Loading, please wait...') ?></span>
      </div>
<?php
  }

  function get_effective_config() {
    if (!self::is_admin()) {
      print Errors::to_json(Errors::E_UNAUTHORIZED);
      return;
    }

    $cfg_instance = new Config();
    $cfg_rc = new ReflectionClass($cfg_instance);

    $cfg_constants = $cfg_rc->getConstants();
    $envvar_prefix = $cfg_rc->getConstant('_ENVVAR_PREFIX');
    $defaults = $cfg_rc->getConstant('_DEFAULTS');

    $params_rc = $cfg_rc->getProperty('params');
    $params_rc->setAccessible(true);

    $params = [];

    foreach ($params_rc->getValue($cfg_instance) as $p => $v) {
      list ($pval, $ptype) = $v;
      $env_val = getenv($envvar_prefix . $p);
      list ($defval, $deftype) = $defaults[$cfg_rc->getConstant($p)];
      $should_redact = in_array($p, self::CONFIG_KEYS_TO_MASK);

      $params[] = [
        'name' => $p,
        'should_redact' => $should_redact,
        'effective_val' => $should_redact ? 'redacted' : strval(self::maybe_bool_to_str($pval)),
        'env_val' => $env_val ? $should_redact ? 'redacted' : $env_val : '',
        'default_val' => strval($defval),
        'type_hint' => self::PARAM_TYPE_TO_NAME[$ptype],
      ];
    }

    print json_encode([
      'envvar_prefix' => $envvar_prefix,
      'params' => $params,
    ]);
  }

  private function is_admin() {
    return ($_SESSION['access_level'] ?? 0) >= 10;
  }

  private function maybe_bool_to_str($val) {
    return $val === true ? 'true' : ($val === false ? 'false' : $val);
  }
}
