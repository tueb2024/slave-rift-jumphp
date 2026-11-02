<?php
require __DIR__.'/balance-sim.php';
$n=max(1,min(100,(int)($argv[1]??10)));
$rows=[];foreach([App\Game\XpCurve::CURRENT,App\Game\XpCurve::WEIGHTED,App\Game\XpCurve::ACCESSIBLE]as$model)
foreach(['faible','moyen','bon']as$profile)for($seed=1;$seed<=$n;$seed++)$rows[]=simulateProfile($profile,$seed,360,'route',null,new App\Game\XpCurve($model));
echo json_encode($rows,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);

