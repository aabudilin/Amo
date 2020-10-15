<?

class AmoApp {

	public $settings = array();
	public $account = array();
	public $sFields = array();
	public $lead_id;

	/*
		$settings

		[RESPONSIBLE_USER] - id ответственного по сделке
		[LEAD_STATUS_ID] - id этапа продаж, куда помещать сделку
		[USER_LOGIN] - логин пользователя Амо
		[USER_HASH] - Хеш для доступа к API
		[SUBDOMAIN] - поддомен портала
	*/

	public function __construct($set) {
		$this->settings = $set;
		$this->auth();
		$this->get_account();
		$this->get_fields();
	}

	public function auth() {
		$link='https://'.$this->settings['SUBDOMAIN'].'.amocrm.ru/private/api/auth.php?type=json';
		$user = array (
			'USER_LOGIN'=> $this->settings['USER_LOGIN'],
			'USER_HASH'=> $this->settings['USER_HASH'],
		);

		$curl = curl_init();
		curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($curl,CURLOPT_USERAGENT,'amoCRM-API-client/1.0');
		curl_setopt($curl,CURLOPT_URL,$link);
		curl_setopt($curl,CURLOPT_POST,true);
		curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($user));
		curl_setopt($curl,CURLOPT_HEADER,false);
		curl_setopt($curl,CURLOPT_COOKIEFILE,dirname(__FILE__).'/cookie.txt'); #PHP>5.3.6 dirname(__FILE__) -> __DIR__
		curl_setopt($curl,CURLOPT_COOKIEJAR,dirname(__FILE__).'/cookie.txt'); #PHP>5.3.6 dirname(__FILE__) -> __DIR__
		curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,0);
		curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,0);
		$out = curl_exec($curl); #Инициируем запрос к API и сохраняем ответ в переменную
		$code = curl_getinfo($curl,CURLINFO_HTTP_CODE); #Получим HTTP-код ответа сервера
		curl_close($curl);  #Завершаем сеанс cURL
		$response = json_decode($out,true);
		//echo '<b>Авторизация:</b>'; echo '<pre>'; print_r($Response); echo '</pre>';
	}

	public function get_account() {
		//ПОЛУЧАЕМ ДАННЫЕ АККАУНТА
		$link = 'https://'.$this->settings['SUBDOMAIN'].'.amocrm.ru/private/api/v2/json/accounts/current'; #$subdomain уже объявляли выше
		$curl = curl_init(); #Сохраняем дескриптор сеанса cURL
		#Устанавливаем необходимые опции для сеанса cURL
		curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($curl,CURLOPT_USERAGENT,'amoCRM-API-client/1.0');
		curl_setopt($curl,CURLOPT_URL,$link);
		curl_setopt($curl,CURLOPT_HEADER,false);
		curl_setopt($curl,CURLOPT_COOKIEFILE,dirname(__FILE__).'/cookie.txt'); #PHP>5.3.6 dirname(__FILE__) -> __DIR__
		curl_setopt($curl,CURLOPT_COOKIEJAR,dirname(__FILE__).'/cookie.txt'); #PHP>5.3.6 dirname(__FILE__) -> __DIR__
		curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,0);
		curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,0);
		$out = curl_exec($curl); #Инициируем запрос к API и сохраняем ответ в переменную
		$code = curl_getinfo($curl,CURLINFO_HTTP_CODE);
		curl_close($curl);
		$response = json_decode($out,true);
		$this->account = $response['response']['account'];
		//echo '<b>Данные аккаунта:</b>'; echo '<pre>'; print_r($Response); echo '</pre>';
	}

	public function get_fields() {
		//ПОЛУЧАЕМ СУЩЕСТВУЮЩИЕ ПОЛЯ
		$amoAllFields = $this->account['custom_fields']; //Все поля
		$amoConactsFields = $this->account['custom_fields']['contacts']; //Поля контактов
		//echo '<b>Поля из амо:</b>'; echo '<pre>'; print_r($amoConactsFields); echo '</pre>';


		//ФОРМИРУЕМ МАССИВ С ЗАПОЛНЕННЫМИ ПОЛЯМИ КОНТАКТА
		//Стандартные поля амо:
		$this->sFields = array_flip(array(
				'PHONE', //Телефон. Варианты: WORK, WORKDD, MOB, FAX, HOME, OTHER
				'EMAIL' //Email. Варианты: WORK, PRIV, OTHER
			)
		);

		//Проставляем id этих полей из базы амо
		foreach($amoConactsFields as $afield) {
			if(isset($this->sFields[$afield['code']])) {
				$this->sFields[$afield['code']] = $afield['id'];
			}
		}
	}

	/*
		Добавление сделки
	*/

	public function create_leads($lead_name) {
		//ДОБАВЛЯЕМ СДЕЛКУ
		$leads['request']['leads']['add']=array(
			array(
				'name' => $lead_name,
				'status_id' => $this->settings['LEAD_STATUS_ID'], //id статуса
				'responsible_user_id' => $this->setting['RESPONSIBLE_USER_ID'], //id ответственного по сделке
				//'date_create'=>1298904164, //optional
				//'price'=>300000,
				//'tags' => 'Important, USA', #Теги
				//'custom_fields'=>array()
			)
		);

		$link='https://'.$this->settings['SUBDOMAIN'].'.amocrm.ru/private/api/v2/json/leads/set';

		$result = $this->response($link, $leads);

		if(is_array($result['RESPONSE']['response']['leads']['add']))
			foreach($result['RESPONSE']['response']['leads']['add'] as $lead) {
				$this->lead_id = $lead["id"]; //id новой сделки
			};
	}

	/*
		Добавление контакта
	*/

	public function create_contact($contact_name,$contact_phone) {
		$contact = array(
			'name' => $contact_name,
			'linked_leads_id' => array($this->lead_id), //id сделки
			'responsible_user_id' => $this->settings['RESPONSIBLE_USER_ID'], //id ответственного
			'custom_fields'=>array(
				array(
					'id' => $this->sFields['PHONE'],
					'values' => array(
						array(
							'value' => $contact_phone,
							'enum' => 'MOB'
						)
					)
				),
				/*array(
					'id' => $this->sFields['EMAIL'],
					'values' => array(
						array(
							'value' => $contact_email,
							'enum' => 'WORK'
						)
					)
				)*/
			)
		);

		$set['request']['contacts']['add'][] = $contact;

		#Формируем ссылку для запроса
		$link = 'https://'.$this->settings['SUBDOMAIN'].'.amocrm.ru/private/api/v2/json/contacts/set';
		
		$result = $this->response($link, $set);
	}

	private function response($link,$data) {
		$curl = curl_init(); 
		curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($curl,CURLOPT_USERAGENT,'amoCRM-API-client/1.0');
		curl_setopt($curl,CURLOPT_URL,$link);
		curl_setopt($curl,CURLOPT_CUSTOMREQUEST,'POST');
		curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($data));
		curl_setopt($curl,CURLOPT_HTTPHEADER,array('Content-Type: application/json'));
		curl_setopt($curl,CURLOPT_HEADER,false);
		curl_setopt($curl,CURLOPT_COOKIEFILE,dirname(__FILE__).'/cookie.txt'); #PHP>5.3.6 dirname(__FILE__) -> __DIR__
		curl_setopt($curl,CURLOPT_COOKIEJAR,dirname(__FILE__).'/cookie.txt'); #PHP>5.3.6 dirname(__FILE__) -> __DIR__
		curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,0);
		curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,0);

		$request = curl_exec($curl); #Инициируем запрос к API и сохраняем ответ в переменную

		$code = curl_getinfo($curl,CURLINFO_HTTP_CODE);
		$response =json_decode($request,true);

		return array (
			'CODE' => $code,
			'RESPONSE' => $response,
		);
	}

}