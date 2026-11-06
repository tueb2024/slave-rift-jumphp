<?php
namespace App\Tests;
use App\Audio\{ProceduralScore,Sampler};
use PHPUnit\Framework\TestCase;
final class ProceduralMusicTest extends TestCase
{
    public function testSeededMotifsRepeatAndEvolveWithoutTouchingGameplayRandomness(): void
    {
        $a=new ProceduralScore(42);$b=new ProceduralScore(42);self::assertSame($a->phrase(0),$b->phrase(0));
        self::assertSame($a->phrase(0),$a->phrase(1));self::assertNotSame($a->phrase(0),$a->phrase(3));
        self::assertNotSame($a->phrase(0),(new ProceduralScore(17))->phrase(0));
        $run=new \App\Game\Run(\App\Game\HordeArena::build(),42);$rng=new \ReflectionProperty($run,'rng');$before=$rng->getValue($run);
        for($i=0;$i<100;$i++)$a->events($i%16,intdiv($i,16),18,true,25);self::assertSame($before,$rng->getValue($run));
    }
    public function testSustainedCounterpointIsConsonantAtStructuralBeatsAndNeverCrosses(): void
    {
        foreach(range(1,64)as$seed)foreach(range(0,15)as$bar){
            $score=new ProceduralScore($seed);$p=$score->phrase($bar);$lead=$counter=0;
            foreach(range(0,15)as$t){$lead=$p['lead'][$t]??$lead;$counter=$p['counter'][$t]??$counter;
                self::assertGreaterThanOrEqual(8,$lead-$counter);
                if($t%4===0)self::assertContains(($lead-$counter)%12,[0,3,4,7,8,9]);
                foreach(['lead'=>$lead,'counter'=>$counter,'bass'=>$p['bass']]as$v=>$n)self::assertStringStartsWith('sr_',Sampler::key($v,$n));
            }
            self::assertLessThan(3,count(array_intersect(array_keys($p['lead']),array_keys($p['counter']))));
        }
    }
    public function testSessionMelodiesDoNotRepeatRecentContoursEvenWithRepeatedCandidate(): void
    {
        $recent=[];$seen=[];
        for($i=0;$i<100;$i++){
            $session=\App\Audio\SessionMelody::choose(42,$recent);
            $signature=\App\Audio\SessionMelody::signature($session['seed']);
            self::assertNotContains($signature,$recent);
            $recent=json_decode(json_encode($session['recent']),true); // persisted/reloaded state
            self::assertLessThanOrEqual(32,count($recent));$seen[$signature]=true;
        }
        self::assertGreaterThanOrEqual(33,count($seen));
    }
    public function testMelodicVocabularyHasMoreThanTwoShapes(): void
    {
        $shapes=[];foreach(range(1,256) as $seed)$shapes[\App\Audio\SessionMelody::signature($seed)]=true;
        self::assertGreaterThan(100,count($shapes));
    }
    public function testLeadStartsAtTwoAndCounterpointAtSix(): void
    {
        $s=new ProceduralScore(42);
        self::assertNotContains('lead',array_column($s->events(0,0,1),0));
        self::assertContains('lead',array_column($s->events(0,0,2),0));
        self::assertNotContains('counter',array_column($s->events(0,0,5),0));
        self::assertContains('counter',array_column($s->events(0,0,6),0));
    }
    public function testSamplerFrequencyAndArrangementProgression(): void
    {
        self::assertEqualsWithDelta(440,Sampler::frequency(69),.001);self::assertEqualsWithDelta(220,Sampler::frequency(57),.001);
        $s=new ProceduralScore(1);self::assertSame([['kick',null],['bass',$s->phrase(0)['bass']]],$s->events(0,0,1));
        self::assertGreaterThan(count($s->events(0,0,1)),count($s->events(0,0,16,true,20)));
        self::assertSame('rift_hat',Sampler::key('hat'));
    }
}
