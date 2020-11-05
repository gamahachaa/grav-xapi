<?php
namespace Grav\Plugin\XapiPlugin;
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Verbs
 *
 * @author bbaudry
 */
use TinCan\Verb;

class Verbs {
    //put your code here
    static public $VERBS =[];
    static public function Voided() {
        self::$VERBS['voided'] = 'http://adlnet.gov/expapi/verbs/voided';
        return new TinCan\Verb(
            [
                'id' => 'http://adlnet.gov/expapi/verbs/voided',
                'display' => [
                    'en-US' => 'voided'
                ]
            ]
        );
    }
}
