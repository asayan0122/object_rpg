RPG 風ゲーム

# Features

10 体モンスターを倒したらゲームクリアです

オブジェクト指向の考えに乗っ取りプログラミングを書いています

直接インスタンス化できない、Creature（生物）という抽象クラスを生成
これを Human と Monster の２種の生物を別クラスに継承させています

またオブジェクトに合わせたプロパティとメソッドを作り、カプセル化も行っています

履歴管理用にインターフェイスのメソッドを元にクラスを作りプレイヤーの声、それぞれの行動記録が画面に表示される仕組みを作りました。

ゲーム調整も兼ね各所で条件分岐を行い全体のバランスを調整しております

プレイヤーはランダムに自動生成されます（今回は３パターン作っています）
プレイヤー側メソッドには攻撃だけでなく、MP を消費する事で、魔法攻撃・回復ができる仕組みを作ってます

モンスターは通常モンスターのクラスを継承させ更に強力なモンスターを作っています

bootstrap にて、簡易ではありますがレスポンシブ対応にもしてあります。
