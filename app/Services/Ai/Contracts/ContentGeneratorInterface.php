<?php

namespace App\Services\Ai\Contracts;

use App\Exceptions\AiProviderException;

interface ContentGeneratorInterface
{
    /**
     * Generates a normalised content plan array from a brief/options payload.
     *
     * @param  array{brand_id?:string,brand_name?:string,business_brief?:string,monthly_brief?:string,options?:array,provider?:string,model?:string,plan_count?:int,posts_per_plan?:int}  $input
     * @return array<int, array{name?:string,strategy?:string,goal?:string,pillars?:array,funnel?:array,formats?:array,posts?:array}>
     *
     * @throws AiProviderException
     */
    public function generatePlan(array $input): array;
}
