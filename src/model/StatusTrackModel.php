<?php
namespace Pegaservice\Homework\model;

class StatusTrackModel{

    const STATUS_A = 'A';
    const STATUS_B = 'B';
    const STATUS_C = 'C';   
    const STATUS_D = 'D';

    public int $id;
    public int $track_no;
    public string $status;
    public string $status_date;

    public MainListModel $MainListModel;
}