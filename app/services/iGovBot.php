<?php
use Phalcon\Di;



class iGovBot extends TinyBot{


    function search($params) {
        global $di;

        $connection=$di->get('pdoconnecton');
        $cache=$di->get('modelsCache');


        $input=$params['input'];
        $user_id=$params['user']->user_id;

        $log = new LegalSearches();
        $log->user_id=$user_id;
        $log->term=$input;
        $log->save();

        $input=trim($input);
        $message='Нічого не знайдено';


        if (mb_strlen($input,'UTF-8')<=2) return array('state'=>'default', 'message'=>'Пошук працює від 3 та більше символів');
        if ((is_numeric($input)) AND (mb_strlen($input,'UTF-8')==8)) {

            $sql="SELECT * FROM legal_entities WHERE code=".$input;
            $res = $connection->query($sql);
            if ($r=$res->fetch()) {
                $message="За кодом <b>".$input."</b> знайдено компанію: \n\n".$this->showCompaniesList(array($r));
            }


        } else {
            $sql="SELECT COUNT(*) AS cs FROM legal_entities WHERE full_name LIKE ".$connection->escapeString('%'.$input.'%');
            $msql=md5($sql);
            $lc=$cache->get($msql);
            if ($lc===null) {
                $res = $connection->query($sql);
                $r=$res->fetch();
                $cache->save($msql, $r['cs']);
                $lc=$r['cs'];
            }



            if ($lc>0) {

                $sql="SELECT * FROM legal_entities WHERE full_name LIKE ".$connection->escapeString('%'.$input.'%')." ORDER BY code  LIMIT 10 ";
                $msql=md5($sql);
                $rows=$cache->get($msql);
                if ($rows===null) {
                    $rows=array();
                    $res = $connection->query($sql);
                    while ($r=$res->fetch()) {
                        $rows[]=$r;
                    }
                    $cache->save($msql, $rows);
                }

                $message="За назвою <b>".$input."</b> знайдено компаній: ". $lc."\n\n";
                if ($lc>10) {
                    $message.="Показано перші 10 \n\n";
                }

                $message.=$this->showCompaniesList($rows);
            } else {
                //ищем по имени директора



                $sql="SELECT COUNT(*) AS cs FROM legal_entities WHERE ceo_name  LIKE ".$connection->escapeString($input.'%');
                $msql=md5($sql);
                $lc=$cache->get($msql);
                if ($lc===null) {
                    $res = $connection->query($sql);
                    $r=$res->fetch();
                    $cache->save($msql, $r['cs']);
                    $lc=$r['cs'];
                }


                if ($lc>0) {

                    $sql="SELECT * FROM legal_entities WHERE ceo_name LIKE ".$connection->escapeString($input.'%')." ORDER BY code  LIMIT 10 ";
                    $msql=md5($sql);
                    $rows=$cache->get($msql);
                    if ($rows===null) {
                        $rows=array();
                        $res = $connection->query($sql);
                        while ($r=$res->fetch()) {
                            $rows[]=$r;
                        }
                        $cache->save($msql, $rows);
                    }

                    $message = "За іменем директора <b>".$input."</b> знайдено компаній — " . $lc . "\n\n";
                    if ($lc>10) {
                        $message.="Показано перші 10 \n\n";
                    }

                    $message.=$this->showCompaniesList($rows);

                }


            }






        }



        return array('state'=>'default', 'message'=>$message);
    }


    function showCompaniesList($list, $maxcount=10)
    {

        $result = array();
        $count = (count($list) > $maxcount) ? $maxcount : count($list);

        for ($i = 0; $i < $count; $i++) {

            $result[] = '<b>' . $list[$i]['full_name'] . '</b>' . "\n" .
                '<i>Код:</i> ' . $list[$i]['code'] . "\n" .
                '<i>Адреса:</i> ' . $list[$i]['location'] . "\n" .
                '<i>Директор:</i> ' . $list[$i]['ceo_name'] . "\n" .
                '<i>Вид діяльності:</i> ' . $list[$i]['activities'] . "\n" .
                '<i>Стан:</i> ' . $list[$i]['status'] . "\n";

        }
        $text = implode("\n\n", $result);

        return $text;

    }


    function subscription($params) {
        $input=$params['input'];
        $input=trim($input);
        $message='Не вдалося підписатися на сповіщення про компанію з кодом '.$input;
        $state='subscription';


        if ((is_numeric($input)) AND (mb_strlen($input,'UTF-8')==8)) {
            $entity_id=intval($input);
            $legal =  LegalEntities::findFirst('code='.$entity_id);
            if ($legal) {
                $user_id=$params['user']->user_id;
                $subs=LegalUsers::findFirst('entity_id='.$entity_id.' AND user_id='.$user_id);
                if ($subs) {
                    if ($subs->status==1) {
                        $message="Вас вже підписано на сповіщення про компанію з кодом ".$entity_id;
                    } else  {
                        $subs->status=1;
                        $subs->save();
                        $message="Вас знову підписано на сповіщення про компанію з кодом ".$entity_id;
                    }

                } else {
                    $subs = new LegalUsers();
                    $subs->user_id=$user_id;
                    $subs->entity_id=$entity_id;
                    $subs->status=1;
                    $subs->save();
                    $message="Вас підписано на сповіщення про компанію з кодом ".$entity_id;
                }
                $state='search';

            } else {
                $message="Компанію з кодом ".$entity_id.' не знайдено, перевірте код та введіть ще раз';
            }
        } else {
            $message="Для підписки необхідний код компанії з 8 цифр, перевірте код та введіть ще раз";
        }

        return array('state'=>$state, 'message'=>$message);
    }

    function unsubscription($params) {
        $input=$params['input'];
        $input=trim($input);
        $state='unsubscription';


        if ((is_numeric($input)) AND (mb_strlen($input,'UTF-8')==8)) {
            $entity_id=intval($input);
            $user_id=$params['user']->user_id;
            $subs=LegalUsers::findFirst('entity_id='.$entity_id.' AND user_id='.$user_id);
            if ($subs) {
                if ($subs->status == 1) {
                    $message = "Ви більше не отримаєте сповіщень про компанію з кодом " . $entity_id;
                    $subs->status = 0;
                    $subs->save();
                } else {
                    $message = "Вас не підписували на сповіщення про компанію з кодом " . $entity_id;
                }
            } else {
                $message = "Вас не підписували на сповіщення про компанію з кодом " . $entity_id;
            }

            $state='search';
        } else {
            $message="Для відписки необхідний код компанії з 8 цифр, перевірте код та введіть ще раз";
        }

        return array('state'=>$state, 'message'=>$message);
    }
}
