<?php return array(
    'root' => array(
        'name' => '__root__',
        'pretty_version' => 'dev-master',
        'version' => 'dev-master',
        'reference' => '10779c4afaf985c1f6f1d4d0597363b3273315f9',
        'type' => 'library',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => true,
    ),
    'versions' => array(
        '__root__' => array(
            'pretty_version' => 'dev-master',
            'version' => 'dev-master',
            'reference' => '10779c4afaf985c1f6f1d4d0597363b3273315f9',
            'type' => 'library',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'appsero/client' => array(
            'pretty_version' => 'v1.2.3',
            'version' => '1.2.3.0',
            'reference' => 'ddfaa9ce62618e0aee4f95ddeb37a27ebd9a3508',
            'type' => 'library',
            'install_path' => __DIR__ . '/../appsero/client',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'roave/security-advisories' => array(
            'pretty_version' => 'dev-latest',
            'version' => 'dev-latest',
            'reference' => '6ea60363203786a5dd5617c28e933280bb61f78b',
            'type' => 'metapackage',
            'install_path' => NULL,
            'aliases' => array(
                0 => '9999999-dev',
            ),
            'dev_requirement' => true,
        ),
    ),
);
