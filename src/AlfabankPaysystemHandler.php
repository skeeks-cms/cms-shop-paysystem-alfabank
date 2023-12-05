<?php
/**
 * @link https://cms.skeeks.com/
 * @copyright Copyright (c) 2010 SkeekS
 * @license https://cms.skeeks.com/license/
 * @author Semenov Alexander <semenov@skeeks.com>
 */

namespace skeeks\cms\shop\alfabank;

use skeeks\cms\shop\models\ShopOrder;
use skeeks\cms\shop\models\ShopPayment;
use skeeks\cms\shop\paysystem\PaysystemHandler;
use skeeks\yii2\form\fields\FieldSet;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\helpers\Url;

/**
 * @author Semenov Alexander <semenov@skeeks.com>
 */
class AlfabankPaysystemHandler extends PaysystemHandler
{
    /**
     * @see https://developer.sberbank.ru/acquiring-api-rest-requests1pay
     */
    const ORDER_STATUS_2 = 2; //Проведена полная авторизация суммы заказа

    public $gatewayUrl = 'https://alfa.rbsuat.com/payment/rest/';

    public $currency = 'RUB';
    public $username = '';
    public $password = '';

    /**
     * Можно задать название и описание компонента
     * @return array
     */
    static public function descriptorConfig()
    {
        return array_merge(parent::descriptorConfig(), [
            'name' => \Yii::t('skeeks/shop/app', 'Alfabank'),
        ]);
    }


    public function rules()
    {
        return ArrayHelper::merge(parent::rules(), [
            [['username'], 'string'],
            [['password'], 'string'],
        ]);
    }

    public function attributeLabels()
    {
        return ArrayHelper::merge(parent::attributeLabels(), [
            'username' => 'Идентификатор магазина из ЛК',
            'password' => 'Пароль',
        ]);
    }

    public function attributeHints()
    {
        return ArrayHelper::merge(parent::attributeHints(), [
        ]);
    }

    /**
     * @return array
     */
    public function getConfigFormFields()
    {
        return [
            'main' => [
                'class'  => FieldSet::class,
                'name'   => 'Основные',
                'fields' => [
                    'username',
                    'password',
                ],
            ],

        ];
    }

    /**
     * @see https://alfabank.ru/sme/payservice/internet-acquiring/docs/connection-options/api/code-example/
     *
     * @param $method
     * @param $data
     * @return mixed
     */
    public function gateway($method, $data)
    {
        \Yii::info("Alfabank gateway: " . $method . ": " . print_r($data, true), static::class);

        $curl = curl_init(); // Инициализируем запрос
        curl_setopt_array($curl, [
            CURLOPT_URL            => $this->gatewayUrl.$method, // Полный адрес метода
            CURLOPT_RETURNTRANSFER => true, // Возвращать ответ
            CURLOPT_POST           => true, // Метод POST
            CURLOPT_POSTFIELDS     => http_build_query($data) // Данные в запросе
        ]);
        $response = curl_exec($curl); // Выполняем запрос

        $response = json_decode($response, true); // Декодируем из JSON в массив
        curl_close($curl); // Закрываем соединение
        return $response; // Возвращаем ответ
    }


    /**
     * @param ShopPayment $shopPayment
     * @return \yii\console\Response|\yii\web\Response
     */
    public function actionPayOrder(ShopOrder $shopOrder)
    {
        $bill = $this->getShopBill($shopOrder);

        $sber = $bill->shopPaySystem->handler;
        $money = $bill->money->convertToCurrency("RUB");


        $returnUrl = $shopOrder->getUrl([], true);
        $successUrl = $shopOrder->getUrl(['success_paied' => true], true);
        $failUrl = $shopOrder->getUrl(['fail_paied' => true], true);

        /**
         * Для чеков нужно указывать информацию о товарах
         * https://yookassa.ru/developers/api?lang=php#create_payment
         */


        if (isset($bill->external_data['formUrl'])) {
            return \Yii::$app->response->redirect($bill->external_data['formUrl']);
        }



        $data = [
            'userName'    => $bill->shopPaySystem->handler->username,
            'password'    => $bill->shopPaySystem->handler->password,
            'description' => "Заказ в магазине №{$bill->shopOrder->id}",
            'orderNumber' => urlencode($bill->id),
            'amount'      => urlencode($bill->money->amount * 100), // передача данных в копейках/центах
            'returnUrl'   => Url::toRoute(['/alfabank/alfabank/success', 'code' => urlencode($bill->code)], true),
            'failUrl'     => Url::toRoute(['/alfabank/alfabank/fail', 'code' => urlencode($bill->code)], true),
        ];

        /*if ($bill->shopOrder && $bill->shopOrder->contact_email) {
            $data['jsonParams'] = '{"email":"'.$bill->shopOrder->contact_email.'"}';
        }*/

        $response = $this->gateway('register.do', $data);

        /*print_r($response);die;*/
        if (isset($response['errorCode'])) { // В случае ошибки вывести ее
            return \Yii::$app->response->redirect(Url::toRoute(['/alfabank/alfabank/fail', 'code' => urlencode($bill->code), 'response' => Json::encode($response)], true));
        } else { // В случае успеха перенаправить пользователя на плетжную форму
            $bill->external_data = $response;
            if (!$bill->save()) {

                //TODO: Add logs
                print_r($bill->errors);
                die;
            }

            return \Yii::$app->response->redirect($bill->external_data['formUrl']);
        }
    }
}