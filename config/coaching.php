<?php

/*
| Coaching skills and score bands.
|
| Add a skill by appending a key and Persian label. The analysis prompt,
| validator, and agent aggregation all read this list.
|
| Per-call AI output is stored on conversation_analyses.coaching_analysis_json.
| Agent scores are calculated from those rows and are not stored again.
|
| Later workflow (sessions, goals, deadlines, acknowledgement, notes,
| weekly plans, team heatmap) can attach to the agent and cite the same
| per-call JSON without changing the analysis pipeline.
*/

return [

    'min_analyzed_calls' => (int) env('COACHING_MIN_ANALYZED_CALLS', 5),

    'evidence_limit' => 8,

    'strength_limit' => 4,

    'recommendation_limit' => 5,

    'trend_skill_limit' => 4,

    'min_trend_points' => 2,

    /*
    | A needs-improvement skill is treated as high severity when at least
    | this share of its scored calls were themselves below the "good" band.
    */
    'high_severity_weak_ratio' => 0.4,

    /*
    | Inclusive upper bounds, lowest band first.
    | These match the agent score colors: 85 / 70 / 50.
    */
    'bands' => [
        ['max' => 49, 'status' => 'critical', 'label' => 'نیاز جدی به بهبود'],
        ['max' => 69, 'status' => 'needs_improvement', 'label' => 'نیاز به بهبود'],
        ['max' => 84, 'status' => 'good', 'label' => 'خوب'],
        ['max' => 100, 'status' => 'strength', 'label' => 'قوت'],
    ],

    'skills' => [
        ['key' => 'call_opening', 'label' => 'شروع تماس'],
        ['key' => 'need_discovery', 'label' => 'کشف نیاز'],
        ['key' => 'active_listening', 'label' => 'گوش دادن فعال'],
        ['key' => 'conversation_management', 'label' => 'مدیریت مکالمه'],
        ['key' => 'product_knowledge', 'label' => 'دانش محصول'],
        ['key' => 'presentation', 'label' => 'ارائه محصول یا خدمت'],
        ['key' => 'objection_handling', 'label' => 'مدیریت اعتراض'],
        ['key' => 'negotiation', 'label' => 'مذاکره'],
        ['key' => 'closing', 'label' => 'بستن فروش'],
        ['key' => 'follow_up', 'label' => 'پیگیری و گام بعدی'],
        ['key' => 'empathy', 'label' => 'همدلی'],
        ['key' => 'dissatisfied_customer', 'label' => 'رسیدگی به مشتری ناراضی'],
        ['key' => 'communication_quality', 'label' => 'کیفیت ارتباط'],
        ['key' => 'compliance', 'label' => 'پایبندی به اسکریپت و الزامات'],
        ['key' => 'call_closing', 'label' => 'جمع‌بندی و پایان تماس'],
    ],

];
