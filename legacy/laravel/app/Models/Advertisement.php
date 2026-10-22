<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Advertisement extends Model
{
    use HasFactory;

    public const POSITIONS = [
        'leaderboard_top' => 'Leaderboard Atas Beranda (970×90 / 728×90)',
        'infeed_home'     => 'In-Feed Beranda (Di Bawah Berita Terkini)',
        'sidebar_home'    => 'Sidebar Kanan Beranda (300×250)',
        'sidebar_article' => 'Sidebar Halaman Baca Artikel (300×250)',
    ];

    protected $fillable = [
        'title',
        'sponsor_name',
        'position',
        'ad_type',
        'image_url',
        'target_url',
        'html_code',
        'is_active',
        'views_count',
        'clicks_count',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'is_active'    => 'boolean',
            'views_count'  => 'integer',
            'clicks_count' => 'integer',
            'start_date'   => 'datetime',
            'end_date'     => 'datetime',
        ];
    }
}
