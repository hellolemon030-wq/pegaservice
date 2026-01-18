<?php
namespace Pegaservice\Homework\model;

class MainListModel{
    public int $id;
    public string $user_name;
    public string $status;

    /**
     * @var StatusTrackModel[]
     */
    public array $statusTracks = [];

    static public function dbRecordToModel(array $record): MainListModel {
        $model = new MainListModel();
        $model->id = (int)$record['id'];
        $model->user_name = $record['user_name'];
        $model->status = $record['status'];
        return $model;
    }
}