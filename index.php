<?php
/******************************************************************************
 *
 * Subrion - open source content management system
 * Copyright (C) 2017 Intelliants, LLC <https://intelliants.com>
 *
 * This file is part of Subrion.
 *
 * Subrion is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Subrion is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Subrion. If not, see <http://www.gnu.org/licenses/>.
 *
 *
 * @link https://subrion.org/
 *
 ******************************************************************************/

if (iaView::REQUEST_HTML == $iaView->getRequestType())
{
	$subjects = $iaCore->get('contact_us_subjects');

	if ($subjects)
	{
		$subjects = explode(PHP_EOL, $subjects);

		foreach ($subjects as $key => $subject)
		{
			$subjects[$key] = trim($subject);
		}
	}

	if (isset($_POST['msg']))
	{
		$error = false;
		$messages = [];

		// min and max message length
		$len = ['min' => 10, 'max' => 500];

		iaUtil::loadUTF8Functions('ascii', 'validation', 'bad', 'utf8_to_ascii');

		// default email address to send contact requests to
		$email_to_send = $iaCore->get('site_email');

		$data = [];
		$data['fullname'] = iaSanitize::html($_POST['name']);
		$data['email'] = iaUtil::checkPostParam('email');
		$data['phone'] = iaUtil::checkPostParam('phone');
		$data['subject'] = isset($_POST['subject']) && $_POST['subject'] ? $_POST['subject'] : iaLanguage::get('contact_request_from') . ' ' . $iaCore->get('site');
		$data['body'] = preg_replace('[\r\n]', '', nl2br(iaSanitize::html($_POST['msg'])));
		$data['ip'] = iaUtil::getIp();
		$body_len = utf8_strlen($data['body']);

		if (empty($data['email']))
		{
			$error = true;
			$messages[] = iaLanguage::getf('field_is_empty', ['field' => iaLanguage::get('email')]);
		}
		elseif (!iaValidate::isEmail($data['email']))
		{
			$error = true;
			$messages[] = iaLanguage::get('error_email_incorrect');
		}

		if (!$data['fullname'])
		{
			$error = true;
			$messages[] = iaLanguage::getf('field_is_empty', ['field' => iaLanguage::get('fullname')]);
		}

		if ($len['min'] > $body_len || $len['max'] < $body_len)
		{
			$error = true;
			$messages[] = iaLanguage::getf('contact_body_len', ['num' => $len['min'] . '-' . $len['max']]);
		}

		if (!iaUsers::hasIdentity() && !iaValidate::isCaptchaValid())
		{
			$error = true;
			$messages[] = iaLanguage::get('confirmation_code_incorrect');
		}

		if (!$error)
		{
			$data = array_map( ['iaSanitize', 'sql'], $data);
			$iaDb->insert($data, ['date' => iaDb::FUNCTION_NOW], 'contacts');

			if ('Email' == $iaCore->get('contact_notif'))
			{
				$iaMailer = $iaCore->factory('mailer');

				// validate config email address
				if (iaValidate::isEmail($iaCore->get('contact_us_email')))
				{
					$email_to_send = $iaCore->get('contact_us_email');
				}

				$data['body'] .= '<br><br>' . $data['fullname'] . '<br>'
					. $data['email'] . '<br>'
					. $data['phone'];

				$iaMailer->AddAddress($email_to_send);

				$iaMailer->Subject = $data['subject'];
				$iaMailer->Body = $data['body'];

				$iaMailer->Send();
			}

			$messages = [iaLanguage::get('message_sent')];
		}

		$error || $_POST = [];
		$iaView->setMessages($messages, $error ? iaView::ERROR : iaView::SUCCESS);
	}

	$iaView->assign('subjects', $subjects);

	$iaView->display();
}