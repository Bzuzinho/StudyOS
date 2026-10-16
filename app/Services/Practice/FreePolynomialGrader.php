<?php

namespace App\Services\Practice;

/**
 * Deterministic equivalence for finite polynomial expressions in x.
 * No remote services, eval, or subprocess. Unsupported input remains ungraded.
 */
class FreePolynomialGrader
{
    public function compare(string $expected, string $submitted, float $maxPoints): array
    {
        $a=$this->parse($expected);
        $b=$this->parse($submitted);
        if ($a===null || $b===null) return [
            'grading_status'=>'pending_review','grading_method'=>'unsupported_expression',
            'score'=>null,'percentage'=>null,
            'feedback'=>'A expressão não pertence ao conjunto de polinómios atualmente suportado. Não foi atribuída classificação.',
        ];
        $equal=true;
        foreach (array_unique(array_merge(array_keys($a),array_keys($b))) as $n)
            if (abs(($a[$n]??0)-($b[$n]??0))>1.0e-8) $equal=false;
        return [
            'grading_status'=>'graded','grading_method'=>'local_polynomial_equivalence',
            'score'=>$equal?$maxPoints:0.0,'percentage'=>$equal?100:0,
            'feedback'=>$equal?'Expressão polinomial equivalente à solução.':'A expressão não é equivalente à solução esperada.',
        ];
    }

    private function parse(string $raw): ?array
    {
        $s=strtolower(str_replace([' ','−','×',','],['','','*','.'],trim($raw)));
        if (strlen($s)>300 || !preg_match('/^[0-9x+*^.\-]+$/D',$s))return null;
        $s=preg_replace('/(?<=\d)x/','*x',$s);
        if ($s==='' || str_contains($s,'**') || str_contains($s,'++'))return null;
        if ($s[0]!=='-' && $s[0]!=='+')$s='+'.$s;
        $terms=preg_split('/(?=[+-])/',substr($s,1),-1,PREG_SPLIT_NO_EMPTY);
        $first=$s[0];
        if ($first==='-')$terms[0]='-'.$terms[0];
        $result=[];
        foreach ($terms as $term) {
            if (!preg_match('/^([+-]?)(?:(\d+(?:\.\d+)?)\*?)?(x(?:\^(\d+))?)?$/D',$term,$m))return null;
            $sign=($m[1]??'')==='-'?-1:1;
            $hasX=isset($m[3]) && $m[3]!=='';
            $power=$hasX?(isset($m[4]) && $m[4]!==''?(int)$m[4]:1):0;
            if ($power>8 || ($hasX&&isset($m[4])&&strlen($m[4])>2))return null;
            if (!$hasX && (!isset($m[2]) || $m[2]===''))return null;
            $value=$hasX && (!isset($m[2]) || $m[2]==='')?1:(float)($m[2]??0);
            $result[$power]=($result[$power]??0)+$sign*$value;
        }
        return $result;
    }
}
