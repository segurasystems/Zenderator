<?php

namespace Zenderator\Generators;

use Zenderator\Interfaces\IZenderatorGenerator;
use Zenderator\Zenderator;

class SwaggerGenerator extends BaseGenerator
{
    private $baseSwagger = array (
        'swagger' => '2.0',
        'info' =>
            array (
                'description' => 'Beep',
                'version' => '1.0.0',
                'title' => 'Swagger Test',
                'termsOfService' => 'TERMS',
                'contact' =>
                    array (
                        'email' => 'emails',
                    ),
                'license' =>
                    array (
                        'name' => 'AN LICENCE',
                        'url' => 'AN URL',
                    ),
            ),
        'host' => 'test.segura.cloud',
        'basePath' => '/v1',
        'tags' =>
            array (
                0 =>
                    array (
                        'name' => 'pet',
                        'description' => 'Everything about your Pets',
                        'externalDocs' =>
                            array (
                                'description' => 'Find out more',
                                'url' => 'http://swagger.io',
                            ),
                    ),
                1 =>
                    array (
                        'name' => 'store',
                        'description' => 'Access to Petstore orders',
                    ),
                2 =>
                    array (
                        'name' => 'user',
                        'description' => 'Operations about user',
                        'externalDocs' =>
                            array (
                                'description' => 'Find out more about our store',
                                'url' => 'http://swagger.io',
                            ),
                    ),
            ),
        'schemes' =>
            array (
                0 => 'https',
                1 => 'http',
            ),
        'paths' =>[],
        'securityDefinitions' =>
            array (
                'petstore_auth' =>
                    array (
                        'type' => 'oauth2',
                        'authorizationUrl' => 'https://petstore.swagger.io/oauth/authorize',
                        'flow' => 'implicit',
                        'scopes' =>
                            array (
                                'write:pets' => 'modify pets in your account',
                                'read:pets' => 'read your pets',
                            ),
                    ),
                'api_key' =>
                    array (
                        'type' => 'apiKey',
                        'name' => 'api_key',
                        'in' => 'header',
                    ),
            ),
        'definitions' =>
            array (
                'Order' =>
                    array (
                        'type' => 'object',
                        'properties' =>
                            array (
                                'id' =>
                                    array (
                                        'type' => 'integer',
                                        'format' => 'int64',
                                    ),
                                'petId' =>
                                    array (
                                        'type' => 'integer',
                                        'format' => 'int64',
                                    ),
                                'quantity' =>
                                    array (
                                        'type' => 'integer',
                                        'format' => 'int32',
                                    ),
                                'shipDate' =>
                                    array (
                                        'type' => 'string',
                                        'format' => 'date-time',
                                    ),
                                'status' =>
                                    array (
                                        'type' => 'string',
                                        'description' => 'Order Status',
                                        'enum' =>
                                            array (
                                                0 => 'placed',
                                                1 => 'approved',
                                                2 => 'delivered',
                                            ),
                                    ),
                                'complete' =>
                                    array (
                                        'type' => 'boolean',
                                        'default' => false,
                                    ),
                            ),
                        'xml' =>
                            array (
                                'name' => 'Order',
                            ),
                    ),
                'User' =>
                    array (
                        'type' => 'object',
                        'properties' =>
                            array (
                                'id' =>
                                    array (
                                        'type' => 'integer',
                                        'format' => 'int64',
                                    ),
                                'username' =>
                                    array (
                                        'type' => 'string',
                                    ),
                                'firstName' =>
                                    array (
                                        'type' => 'string',
                                    ),
                                'lastName' =>
                                    array (
                                        'type' => 'string',
                                    ),
                                'email' =>
                                    array (
                                        'type' => 'string',
                                    ),
                                'password' =>
                                    array (
                                        'type' => 'string',
                                    ),
                                'phone' =>
                                    array (
                                        'type' => 'string',
                                    ),
                                'userStatus' =>
                                    array (
                                        'type' => 'integer',
                                        'format' => 'int32',
                                        'description' => 'User Status',
                                    ),
                            ),
                        'xml' =>
                            array (
                                'name' => 'User',
                            ),
                    ),
                'Category' =>
                    array (
                        'type' => 'object',
                        'properties' =>
                            array (
                                'id' =>
                                    array (
                                        'type' => 'integer',
                                        'format' => 'int64',
                                    ),
                                'name' =>
                                    array (
                                        'type' => 'string',
                                    ),
                            ),
                        'xml' =>
                            array (
                                'name' => 'Category',
                            ),
                    ),
                'Tag' =>
                    array (
                        'type' => 'object',
                        'properties' =>
                            array (
                                'id' =>
                                    array (
                                        'type' => 'integer',
                                        'format' => 'int64',
                                    ),
                                'name' =>
                                    array (
                                        'type' => 'string',
                                    ),
                            ),
                        'xml' =>
                            array (
                                'name' => 'Tag',
                            ),
                    ),
                'Pet' =>
                    array (
                        'type' => 'object',
                        'required' =>
                            array (
                                0 => 'name',
                                1 => 'photoUrls',
                            ),
                        'properties' =>
                            array (
                                'id' =>
                                    array (
                                        'type' => 'integer',
                                        'format' => 'int64',
                                    ),
                                'category' =>
                                    array (
                                        '$ref' => '#/definitions/Category',
                                    ),
                                'name' =>
                                    array (
                                        'type' => 'string',
                                        'example' => 'doggie',
                                    ),
                                'photoUrls' =>
                                    array (
                                        'type' => 'array',
                                        'xml' =>
                                            array (
                                                'name' => 'photoUrl',
                                                'wrapped' => true,
                                            ),
                                        'items' =>
                                            array (
                                                'type' => 'string',
                                            ),
                                    ),
                                'tags' =>
                                    array (
                                        'type' => 'array',
                                        'xml' =>
                                            array (
                                                'name' => 'tag',
                                                'wrapped' => true,
                                            ),
                                        'items' =>
                                            array (
                                                '$ref' => '#/definitions/Tag',
                                            ),
                                    ),
                                'status' =>
                                    array (
                                        'type' => 'string',
                                        'description' => 'pet status in the store',
                                        'enum' =>
                                            array (
                                                0 => 'available',
                                                1 => 'pending',
                                                2 => 'sold',
                                            ),
                                    ),
                            ),
                        'xml' =>
                            array (
                                'name' => 'Pet',
                            ),
                    ),
                'ApiResponse' =>
                    array (
                        'type' => 'object',
                        'properties' =>
                            array (
                                'code' =>
                                    array (
                                        'type' => 'integer',
                                        'format' => 'int32',
                                    ),
                                'type' =>
                                    array (
                                        'type' => 'string',
                                    ),
                                'message' =>
                                    array (
                                        'type' => 'string',
                                    ),
                            ),
                    ),
            ),
        'externalDocs' =>
            array (
                'description' => 'Find out more about Swagger',
                'url' => 'http://swagger.io',
            ),
    );
    
    public function generateFromRoutes(array $routes)
    {
        $this->putFile(true,"swagger.json",json_encode($routes,JSON_PRETTY_PRINT));
    }
}