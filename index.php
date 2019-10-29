<?php
//============================
//ログ吐き出し・セッション用意
//============================
ini_set('log_errors', 'on');
ini_set('error_log', 'php.log');
session_start();
//session_destroy();

//============================
//デバッグ
//============================
$debug_flg = true;
function debug($str)
{
    global $debug_flg;
    if (!empty($debug_flg)) {
        error_log('デバッグ：'.$str);
    }
}
//============================
//変数
//============================
//スタート画面を選択
$startFlg = '';
//通常攻撃を選択し
$attackFlg = '';
//魔法攻撃を選択
$magicFlg = '';
//回復魔法を選択
$healFlg = '';
//あきらめるを選択
$restartFlg = '';
//逃げるを選択
$escapeFlg = '';
//ゲームクリアした時
$gameClearFlg='';

//============================
//配列
//============================
//プレイヤー
$humans = array();
//モンスター
$monsters = array();

//============================
//共通クラス
//============================
abstract class Creature
{
    protected $name;
    protected $hp;
    protected $attackMin;
    protected $attackMax;
    public function setName($str)
    {
        $this->name = $str;
    }
    public function getName()
    {
        return $this->name;
    }
    public function setHp($num)
    {
        $this->hp = $num;
    }
    public function getHp()
    {
        return $this->hp;
    }
    public function attack($targetObj)
    {
        $attackPoint = mt_rand($this->attackMin, $this->attackMax);
        if (!mt_rand(0, 9)) { //10分の1の確率でクリティカル
            $attackPoint = $attackPoint * mt_rand(2, 3);
            $attackPoint = (int)$attackPoint;
            History::set($this->getName().'は会心の一撃!!');
        }
        $targetObj->setHp($targetObj->getHp()-$attackPoint);
        History::set($attackPoint.'ポイントのダメージ！');
    }
}

//============================
//プレイヤークラス
//============================
class Human extends Creature
{
    protected $mp;
    protected $attackVoice;
    protected $damageVoice;
    public function __construct($name, $hp, $mp, $attackVoice, $damageVoice_1, $damageVoice_2, $attackMin, $attackMax)
    {
        //パラメーター
        $this->name = $name;
        $this->hp = $hp;
        $this->mp = $mp;
        $this->attackVoice = $attackVoice;
        $this->damageVoice_1 = $damageVoice_1;
        $this->damageVoice_2 = $damageVoice_2;
        $this->attackMin = $attackMin;
        $this->attackMax = $attackMax;
    }
    //MP
    public function setMp($num)
    {
        $this->mp = $num;
    }
    public function getMp()
    {
        return $this->mp;
    }
    //攻撃時の声
    public function attackVoice()
    {
        return $this->attackVoice;
    }
    //ダメージ時の声(2パターン）
    public function damageVoice()
    {
        if (!mt_rand(0, 1)) {
            return $this->damageVoice_1;
        } else {
            return $this->damageVoice_2;
        }
    }
    //魔法攻撃（MP50消費）
    //プレイヤーの攻撃値を按分した確率×3-5倍の火力
    public function getHumanMagicAttack($targetObj)
    {
        //50MP以上無いと発動しない
        if ($_SESSION['human']->getMp() >= 50) {
            $attackPoint = mt_rand($this->attackMin, $this->attackMax);
            $magicAttack = $attackPoint * mt_rand(2, 4);
            $magicAttack = (int)$magicAttack;
            $targetObj->setHp($targetObj->getHp()-$magicAttack);
            $this->setMp($this->getMp()-50);
            History::set($this->attackVoice());
            History::set($this->getName().'はMPを50ポイント使い魔法攻撃を行った!!');
            History::set($targetObj->getName()."に".$magicAttack.'ポイントのダメージを与えた！');
        } else {
            //MPが不足する場合は、通常攻撃に切り替える
            History::set('MPが足りません!!');
            $attackPoint = mt_rand($this->attackMin, $this->attackMax);
            $targetObj->setHp($targetObj->getHp()-$attackPoint);
            History::set($this->attackVoice().$_SESSION['monster']->getName()."に".$attackPoint.'ポイントのダメージを与えた！');
        }
        if ($_SESSION['human']->getHp() <= 0) {
            $_SESSION = array();
        }
    }

    //回復魔法（MP50消費）:体力を全回復
    public function toHeal()
    {   //50MP以上ないと発動しない
        if ($_SESSION['human']->getMp() >= 50) {
            $this->setHp($this->hp = 3000);
            $this->setMp($this->getMp()-50);
            History::set($this->getName().'はMPを50ポイント使い回復魔法を唱えた!!');
            History::set($this->getName().'は全回復した!');
        } else {
            //MPが不足する場合は、自動で通常攻撃に切り替える
           
            $attackPoint = mt_rand($this->attackMin, $this->attackMax);
            $_SESSION['monster']->setHp($_SESSION['monster']->getHp()-$attackPoint);
            History::set('MPが足りません!!');
            History::set($this->attackVoice().$_SESSION['monster']->getName()."に".$attackPoint.'ポイントのダメージを与えた！');
        }
    }
}

//============================
//モンスタークラス
//============================
class Monster extends Creature
{
    protected $img;
    public function __construct($name, $hp, $img, $attackMin, $attackMax)
    {
        $this->name = $name;
        $this->hp = $hp;
        $this->img = $img;
        $this->attackMin = $attackMin;
        $this->attackMax = $attackMax;
    }
    public function getImg()
    {
        return $this->img;
    }
}
//============================
//モンスタークラス継承
//============================
class StrongMonster extends Monster
{
    private $magicAttack;
    public function __construct($name, $hp, $img, $attackMin, $attackMax, $magicAttack)
    {
        parent::__construct($name, $hp, $img, $attackMin, $attackMax);
        $this->magicAttack = $magicAttack;
    }
    public function getMonsterMagicAttack()
    {
        return $this->magicAttack;
    }
    public function attack($targetObj)
    {
        //1/3で魔法攻撃
        if (!mt_rand(0, 2)) {
            History::set($this->name.'がフレアを発動!!');
            $targetObj->setHp($targetObj->getHp() - $this->magicAttack);
            History::set($this->magicAttack.'ポイントのダメージを受けた！');
        //1/4で2回攻撃
        } elseif (!mt_rand(0, 3)) {
            History::set($this->name.'の２回攻撃!!');
            $targetObj->setHp($targetObj->getHp() - $this->attackMax);
            $targetObj->setHp($targetObj->getHp() - $this->attackMax);
            $doubleAttack = ($this->attackMax*2);
            History::set($doubleAttack.'ポイントのダメージを受けた！');
        } else {
            parent::attack($targetObj);
        }
    }
}

//============================
//ステータス管理
//============================
//セットと消去のメソッド定義
interface HistoryInterface
{
    public static function set($str);
    public static function clear();
}
class History implements HistoryInterface
{
    public static function set($str)
    {
        if (empty($_SESSION['history'])) {
            $_SESSION['history'] = '';
        }
        $_SESSION['history'] .= $str.'<br>';
    }
    public static function clear()
    {
        unset($_SESSION['history']);
    }
}

//============================
//インスタンス生成
//============================
//プレイヤー:$name, $hp, $mp, $attackVoice, $damageVoice_1, $damageVoice_2,$attackMin, $attackMax
$humans[] = new Human('あなた', 3000, 600, '▷▷竜の爪牙に 全てを懸ける！', '▷▷ぐぁぁぁ…', '▷▷油断したか…', 400, 700);
$humans[] = new Human('あなた', 3000, 1000, '▷▷世界の希望のために！！', '▷▷召喚士なのに 情けないな…', '▷▷みんな…ごめん…', 300, 500);
$humans[] = new Human('あなた', 3000, 800, '▷▷その身に刻め…！', '▷▷終わらない…まだ…', '▷▷油断したか…', 300, 500);
$humans[] = new Human('あなた', 3000, 800, '▷▷憂鬱な仕事だ！', '▷▷ここで 幕切れなのか…？', '▷▷真っ白だ…', 400, 800);
//var_dump($humans);

//モンスター:$name, $hp, $img, $attackMin, $attackMax(,$magicAttack)
$monsters[] = new Monster('ダークナイト', 2000, 'img/monsters/darknight.png', 200, 400);
$monsters[] = new Monster('デーモン', 2500, 'img/monsters/darkdemon.png', 200, 350);
$monsters[] = new Monster('リビングソード', 3000, 'img/monsters/ribingsode.png', 200, 300);
$monsters[] = new Monster('ドラゴニア', 2000, 'img/monsters/dragonia.png', 200, 300);
$monsters[] = new Monster('妖術士', 3500, 'img/monsters/youjyutu.png', 80, 150);
$monsters[] = new Monster('サキュバス', 200, 'img/monsters/sakyubas.png', 100, 300);
$monsters[] = new Monster('ジョーカー', 1500, 'img/monsters/joker.png', 100, 250);
$monsters[] = new Monster('アサシン', 2000, 'img/monsters/asashin.png', 150, 300);
$monsters[] = new StrongMonster('神龍', 5000, 'img/monsters/sinryu.png', 400, 500, 600);
$monsters[] = new StrongMonster('ダークマター', 5000, 'img/monsters/darkmatar.png', 500, 600, 700);
//var_dump($monsters);

//============================
//関数
//============================
//プレイヤー生成
function createHuman()
{
    global $humans;
    $human =  $humans[mt_rand(0, 3)];
    $_SESSION['human'] = $human;
}
//モンスター生成
function createMonster()
{
    global $monsters;
    $monster =  $monsters[mt_rand(0, 9)];
    History::set('▷'.$monster->getName().'が現れた！');
    $_SESSION['monster'] = $monster;
}
//ゲームスタート用
function init()
{
    History::clear();
    $_SESSION['clearCount'] = 0;
    createHuman();
    createMonster();
}
//逃げる(確率1/2で失敗)
function escapeChance()
{
    if (mt_rand(0, 3)) {
        History::set($_SESSION['monster']->getName().'から逃げ出した！');
        createMonster();
    } else {
        History::set($_SESSION['monster']->getName().'に回り込まれた！');
        $_SESSION['monster']->attack($_SESSION['human']);
        History::set($_SESSION['human']->damageVoice());
    }
}

//============================
//ゲーム進行
//============================

//1:POST送信されていた場合==============================
if (!empty($_POST)) {
    //POSTした各種フラグを定義==============================
    //を選択した時の動作
    $startFlg = (!empty($_POST['start'])) ? true : false;
    //通常攻撃を選択した時の動作
    $attackFlg = (!empty($_POST['attack'])) ? true : false;
    //魔法攻撃を選択した時の動作
    $magicFlg = (!empty($_POST['magic'])) ? true : false;
    //回復魔法を選択した時の動作
    $healFlg = (!empty($_POST['heal'])) ? true : false;
    //あきらめるを選択した時の動作
    $restartFlg = (!empty($_POST['restart'])) ? true : false;
    //逃げるを選択した時の動作
    $escapeFlg = (!empty($_POST['escape'])) ? true : false;
    //ゲームクリアした時の動作
    $gameClearFlg = ($_SESSION['clearCount'] >= 10) ? true : false;

    //エラーログとPOSTフラグの確認 0or1==============================
    error_log('POST通信');
}
    debug('session' .print_r($_SESSION, true));
    debug('start:' .print_r($startFlg, true));
    debug('attack:' .print_r($attackFlg, true));
    debug('magic:' .print_r($magicFlg, true));
    debug('heal:' .print_r($healFlg, true));
    debug('restart:' .print_r($restartFlg, true));
    debug('escape:' .print_r($escapeFlg, true));
    debug('gameclear:' .print_r($gameclearFlg, true));


    //ゲームをスタート==============================
    if ($startFlg) {
        init();
    } else {
        //ここから戦闘画面に移る==============================
        //敵を倒した回数を毎回判定============================
        //10回倒した時点でクリアフラグが立ちクリア画面へ遷移
        if ($_SESSION['clearCount'] >= 10) {
            $gameClearFlg = true;
            History::clear();

        //クリアフラグが立つまで以下をループ================================
        } else {
            History::clear();
            // 通常攻撃を選択=============================
            if ($attackFlg) {
                //プレイヤー->モンスター
                History::set($_SESSION['human']->getName().'の攻撃！');
                $_SESSION['human']->attack($_SESSION['monster']);


                //モンスター->プレイヤー
                History::set($_SESSION['monster']->getName().'の攻撃！');
                $_SESSION['monster']->attack($_SESSION['human']);
                History::set($_SESSION['human']->damageVoice()); //ダメージ時には声
                // 自分のhpが0以下になったらゲームオーバー
                if ($_SESSION['human']->getHp() <= 0) {
                    $_SESSION = array();
                } else {
                    // モンスターのHPが0以下になったら、別のモンスターを出現
                    //クリアカウントを足す
                    if ($_SESSION['monster']->getHp() <= 0) {
                        History::set($_SESSION['monster']->getName().'を倒した！');
                        createMonster();
                        $_SESSION['clearCount'] = $_SESSION['clearCount']+1; //クリアカウントを足していく
                    }
                }


                //魔法攻撃を選択（MPを50消費する)==============================
            } elseif ($magicFlg) {
                //まずプレイヤーのHPが0以下かどうかを判定
                if ($_SESSION['human']->getHp() <= 0) {
                    $_SESSION = array();
                } else {
                    //HPが0以上の場合、プレイヤーの魔法メソッドを呼び出しモンスターを攻撃
                    $_SESSION['human']->getHumanMagicAttack($_SESSION['monster']);
                    //攻撃後、モンスターのHPが0以下なら、
                    if ($_SESSION['monster']->getHp() <= 0) {
                        History::set($_SESSION['monster']->getName().'を倒した！');

                        createMonster();
                        $_SESSION['clearCount'] = $_SESSION['clearCount']+1;
                    } else {
                        //モンスターのHPが0以上であれば、プレイヤーに攻撃
                        History::clear();
                        History::set($_SESSION['monster']->getName().'の攻撃！');
                        $_SESSION['monster']->attack($_SESSION['human']);
                        History::set($_SESSION['human']->damageVoice());
                        //その時点でHPがあるかどうかを再度確認
                        if ($_SESSION['human']->getHp() <= 0) {
                            $_SESSION = array();
                        }
                    }
                }

                //回復呪文を選択（MPを50消費する)==============================
            } elseif ($healFlg) {
                $_SESSION['human']->toHeal($_SESSION['human']);
                if ($_SESSION['human']->getHp() <= 0) {
                    $_SESSION = array();
                } else {
                    // モンスターがshpが0以下になったら、別のモンスターを出現
                    if ($_SESSION['monster']->getHp() <= 0) {
                        History::set($_SESSION['monster']->getName().'を倒した！');

                        createMonster();
                        $_SESSION['clearCount'] = $_SESSION['clearCount']+1;
                    } else {
                        History::set($_SESSION['monster']->getName().'の攻撃！');
                        $_SESSION['monster']->attack($_SESSION['human']);
                        History::set($_SESSION['human']->damageVoice());
                        if ($_SESSION['human']->getHp() <= 0) {
                            $_SESSION = array();
                        }
                    }
                }
                //逃げるを選択（1/2で失敗するので気をつける）==============================
            } elseif ($escapeFlg) {
                escapeChance();

            //ゲームクリア時/あきらめるを選択（アラートで確認する）==============================
            } elseif ($restartFlg) {
                History::clear();
                session_destroy();
            }
        }
        $_POST = array();
    }
?>

<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <link href="https://fonts.googleapis.com/css?family=Roboto&display=swap" rel="stylesheet">
  <link href="./css/bootstrap.min.css" rel="stylesheet">
  <link href="./css/app.css" rel="stylesheet">
  <title>Object_RPG_Quest</title>
</head>

<body>
  <!--初期画面-->
  <?php if (empty($_SESSION) || ($restartFlg)) { ?>
  <div class="container start-container">
    <div class="row">
      <div class="start-wrap__display col-12">
        <p>RPG風クエスト</p>
        <form method="post" class="start-wrap__display--action">
          <input type="submit" name="start" value="▷ゲームスタート">
        </form>
      </div>
    </div>
  </div>
  <!--ゲームクリア画面-->
  <?php } elseif ($gameClearFlg) { ?>
  <div class="container clear-container">
    <div class="row">
      <div class="clear-wrap__display col-12">
        <p>ゲームクリア!!</p>
        <form method="post" class="clear-wrap__display--action">
          <input type="submit" name="restart" value="▷スタート画面へ戻る">
        </form>
      </div>
    </div>
  </div>
  <!--戦闘画面-->
  <?php  } elseif (($startFlg)||(!empty($_SESSION))) { ?>
  <section class="battle-container">

    <div class="container monster-wrap">
      <div class="row monster-wrap__display">
        <div class="col-3 monster-wrap__display--status">
          <p>
            <?php echo 'HP：'.$_SESSION['monster']->getHp(); ?></p>
        </div>
        <div class="col-10 monster-wrap__display--img">
          <img src="<?php echo $_SESSION['monster']->getImg(); ?>">
        </div>
      </div>
    </div>
    <!--プレイヤーステータス-->
    <div class="container status-wrap">
      <div class="row status-wrap__inner ">
        <div class="status-wrap__display col-11 col-md-4 col-xl-3">
          <div class="status-wrap__display--info">
            <p><?php echo $_SESSION['human']->getName().'のHP：'.$_SESSION['human']->getHp(); ?></p>
            <p><?php echo $_SESSION['human']->getName().'のMP：'.$_SESSION['human']->getMp(); ?></p>
            <p>ゲームクリアまで残り：<?php echo 10 - $_SESSION['clearCount']; ?>体</p>
          </div>
        </div>
        <div class="status-wrap__display col-11 col-md-4 col-xl-3">
          <form method="post" class="status-wrap__display--action">
            <input type="submit" name="attack" value="▶通常攻撃">
            <input type="submit" name="magic" value="▶︎魔法攻撃 ▷MP50">
            <input type="submit" name="heal" value="▶回復呪文 ▷MP50">
            <input type="submit" name="escape" value="▶逃げる">
            <input type="submit" name="restart" value="▶あきらめる" onclick="alert('また挑戦してください')">
          </form>
        </div>
        <div class="status-wrap__display col-11 col-md-4 col-xl-5">
          <div class="status-wrap__display--history">
            <p class="status-wrap__display--split js_history_str_split">
              <?php echo (!empty($_SESSION['history'])) ? $_SESSION['history'] : ''; ?>
            </p>
          </div>
        </div>
  </section>
  <?php } ?>
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js">
  </script>
  <script src="./js/bootstrap.min.js"></script>
  <script src="./js/app.js">
  </script>
</body>

</html>