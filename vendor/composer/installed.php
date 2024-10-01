<?php return array(
    'root' => array(
        'name' => '__root__',
        'pretty_version' => 'dev-master',
        'version' => 'dev-master',
        'reference' => '1c75f6f34aff2dba382b67bb0865fd05195b6ec9',
        'type' => 'library',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => true,
    ),
    'versions' => array(
        '__root__' => array(
            'pretty_version' => 'dev-master',
            'version' => 'dev-master',
            'reference' => '1c75f6f34aff2dba382b67bb0865fd05195b6ec9',
            'type' => 'library',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'appsero/client' => array(
            'pretty_version' => 'v1.4.0',
            'version' => '1.4.0.0',
            'reference' => '43289d79f1d55de687f667b17a2834b986cc7b6e',
            'type' => 'library',
            'install_path' => __DIR__ . '/../appsero/client',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'roave/security-advisories' => array(
            'pretty_version' => 'dev-latest',
            'version' => 'dev-latest',
            'reference' => '6f63660a573ec1e6d48d54ff7c44dfb1e303f30d',
            'type' => 'metapackage',
            'install_path' => null,
            'aliases' => array(
                0 => '9999999-dev',
            ),
            'dev_requirement' => true,
        ),
    ),
);
