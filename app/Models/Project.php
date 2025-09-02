use Illuminate\Database\Eloquent\Concerns\HasUlids;

class Project extends Model {
    use HasUlids;
    public $incrementing = false;
    protected $keyType = 'string';
}