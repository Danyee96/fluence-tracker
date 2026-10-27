<?php
/**
 * Roadmap definition — edit phases/steps here.
 * Keys are stored in the database, so don't rename a key once creators have data
 * (changing the title text is fine).
 */
return [
    ['key' => 'content', 'num' => 'PHASE 01', 'name' => 'Content', 'short' => 'Content', 'miles' => [
        ['key' => 'audio',    't' => 'Audio dialed in'],
        ['key' => 'video',    't' => 'Video quality up'],
        ['key' => 'cadence',  't' => 'Cadence locked'],
        ['key' => 'collabs',  't' => 'Collaborations started', 'unlock' => 'unlock'],
    ]],
    ['key' => 'info', 'num' => 'PHASE 02', 'name' => 'Info Product', 'short' => 'Info Product', 'miles' => [
        ['key' => 'lead_magnet', 't' => 'Free lead magnet live'],
        ['key' => 'low_ticket',  't' => 'Low-ticket offer live'],
        ['key' => 'high_ticket', 't' => 'Mid / high-ticket live'],
    ]],
    ['key' => 'sponsors', 'num' => 'PHASE 03', 'name' => 'Sponsors', 'short' => 'Sponsors', 'miles' => [
        ['key' => 'recurring_sponsor', 't' => 'First recurring sponsor'],
        ['key' => 'affiliates',        't' => 'Affiliate deals set up'],
        ['key' => 'one_off',           't' => 'One-off placements'],
    ]],
    ['key' => 'scale', 'num' => 'PHASE 04', 'name' => 'Scale & Systemize', 'short' => 'Scale', 'miles' => [
        ['key' => 'systems',     't' => 'Systems & team in place'],
        ['key' => 'diversified', 't' => 'Revenue diversified'],
        ['key' => 'brand_irl',   't' => 'Brand & IRL plays'],
    ]],
];
