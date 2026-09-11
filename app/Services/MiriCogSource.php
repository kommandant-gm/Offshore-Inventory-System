<?php
namespace App\Services;

use App\Models\{MajorEquipment, MiriRentalItem, MiriConstructionItem, MiriPaintItem};
use Illuminate\Validation\ValidationException;

class MiriCogSource
{
    public const TYPES = ['Major equipment', 'Rental', 'Construction', 'Paint'];
    public function query(string $type, int $branch)
    {
        $class = match ($type) {
            'Major equipment' => MajorEquipment::class, 'Rental' => MiriRentalItem::class,
            'Construction' => MiriConstructionItem::class, 'Paint' => MiriPaintItem::class,
            default => throw ValidationException::withMessages(['type' => 'Unknown register.']),
        };
        $columns = match ($type) {
            'Major equipment' => ['tag_no','unit','size_model','model_brand','serial_no','mr_request'],
            'Rental' => ['serial_tag_equipment_no','unit','mr_no'],
            'Construction' => ['tag_no','unit','model_brand','serial_no','mr_reference'],
            'Paint' => ['batch_no','mr_reference'],
        };
        return $class::query()->where('branch_id',$branch)->select(['id','branch_id','description','current_location',...$columns]);
    }
    public function identifier(string $type): string
    {
        return match ($type) { 'Rental' => 'serial_tag_equipment_no', 'Paint' => 'batch_no', default => 'tag_no' };
    }
    public function snapshot($model, string $type): array
    {
        return [
            'identifier' => $type === 'Paint' ? null : $model->{$this->identifier($type)},
            'description' => $model->description,
            'size_model' => $model->size_model ?: $model->model_brand,
            'serial_no' => $model->serial_no,
            'batch_no' => $type === 'Paint' ? $model->batch_no : null,
            'mr_reference' => $model->mr_reference ?: ($model->mr_request ?: $model->mr_no),
            'unit' => $type === 'Paint' ? null : $model->unit,
            'current_location' => $model->current_location,
        ];
    }
}
