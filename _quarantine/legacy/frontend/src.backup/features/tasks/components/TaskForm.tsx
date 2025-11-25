import React, { useState, useEffect } from 'react';
import {
  Form,
  Input,
  Select,
  DatePicker,
  InputNumber,
  Switch,
  Button,
  Row,
  Col,
  Card,
  Divider,
  Upload,
  Tag,
  Space,
  Alert,
  Tooltip,
  Progress
} from 'antd';
import {
  PlusOutlined,
  MinusCircleOutlined,
  UploadOutlined,
  InfoCircleOutlined,
  CalendarOutlined,
  UserOutlined,
  FlagOutlined,
  LinkOutlined
} from '@ant-design/icons';
import { Task, User, Project, Component } from '../../types';
import dayjs from 'dayjs';

const { TextArea } = Input;
const { Option } = Select;
const { RangePicker } = DatePicker;

interface TaskFormProps {
  task?: Task;
  projects: Project[];
  users: User[];
  components: Component[];
  availableTasks: Task[]; // For dependencies
  onSubmit: (taskData: Partial<Task>) => void;
  onCancel: () => void;
  loading?: boolean;
  mode: 'create' | 'edit';
}

const TaskForm: React.FC<TaskFormProps> = ({
  task,
  projects,
  users,
  components,
  availableTasks,
  onSubmit,
  onCancel,
  loading = false,
  mode
}) => {
  const [form] = Form.useForm();
  const [selectedProject, setSelectedProject] = useState<string | undefined>(task?.project_id);
  const [selectedComponent, setSelectedComponent] = useState<string | undefined>(task?.component_id);
  const [dependencies, setDependencies] = useState<string[]>(task?.dependencies || []);
  const [attachments, setAttachments] = useState<any[]>([]);
  const [estimatedHours, setEstimatedHours] = useState<number>(task?.estimated_hours || 0);
  const [actualHours, setActualHours] = useState<number>(task?.actual_hours || 0);

  useEffect(() => {
    if (task && mode === 'edit') {
      form.setFieldsValue({
        name: task.name,
        description: task.description,
        project_id: task.project_id,
        component_id: task.component_id,
        status: task.status,
        priority: task.priority,
        assigned_to: task.assigned_to?.id,
        start_date: task.start_date ? dayjs(task.start_date) : null,
        end_date: task.end_date ? dayjs(task.end_date) : null,
        estimated_hours: task.estimated_hours,
        actual_hours: task.actual_hours,
        progress: task.progress,
        is_milestone: task.is_milestone,
        conditional_tag: task.conditional_tag,
        tags: task.tags || []
      });
      setSelectedProject(task.project_id);
      setSelectedComponent(task.component_id);
      setDependencies(task.dependencies || []);
      setEstimatedHours(task.estimated_hours || 0);
      setActualHours(task.actual_hours || 0);
    }
  }, [task, mode, form]);

  const handleProjectChange = (projectId: string) => {
    setSelectedProject(projectId);
    setSelectedComponent(undefined);
    form.setFieldValue('component_id', undefined);
  };

  const handleSubmit = (values: any) => {
    const taskData = {
      ...values,
      dependencies,
      start_date: values.start_date?.format('YYYY-MM-DD'),
      end_date: values.end_date?.format('YYYY-MM-DD'),
      estimated_hours: estimatedHours,
      actual_hours: actualHours,
      attachments: attachments.map(file => file.response?.url || file.url).filter(Boolean)
    };
    onSubmit(taskData);
  };

  const filteredComponents = components.filter(comp => comp.project_id === selectedProject);
  const filteredTasks = availableTasks.filter(t => 
    t.project_id === selectedProject && t.id !== task?.id
  );

  const statusOptions = [
    { value: 'pending', label: 'Chờ xử lý', color: 'blue' },
    { value: 'in_progress', label: 'Đang thực hiện', color: 'processing' },
    { value: 'completed', label: 'Hoàn thành', color: 'success' },
    { value: 'on_hold', label: 'Tạm dừng', color: 'warning' },
    { value: 'cancelled', label: 'Hủy bỏ', color: 'error' }
  ];

  const priorityOptions = [
    { value: 'low', label: 'Thấp', color: 'green' },
    { value: 'medium', label: 'Trung bình', color: 'orange' },
    { value: 'high', label: 'Cao', color: 'red' },
    { value: 'critical', label: 'Khẩn cấp', color: 'red' }
  ];

  return (
    <Card 
      title={
        <Space>
          <FlagOutlined />
          {mode === 'create' ? 'Tạo nhiệm vụ mới' : 'Chỉnh sửa nhiệm vụ'}
        </Space>
      }
      className="task-form-card"
    >
      <Form
        form={form}
        layout="vertical"
        onFinish={handleSubmit}
        initialValues={{
          status: 'pending',
          priority: 'medium',
          progress: 0,
          is_milestone: false
        }}
      >
        {/* Basic Information */}
        <Card size="small" title="Thông tin cơ bản" className="mb-4">
          <Row gutter={16}>
            <Col span={12}>
              <Form.Item
                name="name"
                label="Tên nhiệm vụ"
                rules={[{ required: true, message: 'Vui lòng nhập tên nhiệm vụ' }]}
              >
                <Input placeholder="Nhập tên nhiệm vụ" />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item
                name="project_id"
                label="Dự án"
                rules={[{ required: true, message: 'Vui lòng chọn dự án' }]}
              >
                <Select
                  placeholder="Chọn dự án"
                  onChange={handleProjectChange}
                  showSearch
                  optionFilterProp="children"
                >
                  {projects.map(project => (
                    <Option key={project.id} value={project.id}>
                      {project.name}
                    </Option>
                  ))}
                </Select>
              </Form.Item>
            </Col>
          </Row>

          <Row gutter={16}>
            <Col span={24}>
              <Form.Item
                name="description"
                label="Mô tả"
              >
                <TextArea 
                  rows={4} 
                  placeholder="Mô tả chi tiết về nhiệm vụ"
                  showCount
                  maxLength={1000}
                />
              </Form.Item>
            </Col>
          </Row>

          <Row gutter={16}>
            <Col span={8}>
              <Form.Item
                name="component_id"
                label="Thành phần"
              >
                <Select
                  placeholder="Chọn thành phần"
                  allowClear
                  disabled={!selectedProject}
                  onChange={setSelectedComponent}
                >
                  {filteredComponents.map(component => (
                    <Option key={component.id} value={component.id}>
                      {component.name}
                    </Option>
                  ))}
                </Select>
              </Form.Item>
            </Col>
            <Col span={8}>
              <Form.Item
                name="status"
                label="Trạng thái"
              >
                <Select placeholder="Chọn trạng thái">
                  {statusOptions.map(status => (
                    <Option key={status.value} value={status.value}>
                      <Tag color={status.color}>{status.label}</Tag>
                    </Option>
                  ))}
                </Select>
              </Form.Item>
            </Col>
            <Col span={8}>
              <Form.Item
                name="priority"
                label="Độ ưu tiên"
              >
                <Select placeholder="Chọn độ ưu tiên">
                  {priorityOptions.map(priority => (
                    <Option key={priority.value} value={priority.value}>
                      <Tag color={priority.color}>{priority.label}</Tag>
                    </Option>
                  ))}
                </Select>
              </Form.Item>
            </Col>
          </Row>
        </Card>

        {/* Assignment & Timeline */}
        <Card size="small" title="Phân công & Thời gian" className="mb-4">
          <Row gutter={16}>
            <Col span={8}>
              <Form.Item
                name="assigned_to"
                label="Người thực hiện"
              >
                <Select
                  placeholder="Chọn người thực hiện"
                  allowClear
                  showSearch
                  optionFilterProp="children"
                >
                  {users.map(user => (
                    <Option key={user.id} value={user.id}>
                      <Space>
                        <UserOutlined />
                        {user.name}
                      </Space>
                    </Option>
                  ))}
                </Select>
              </Form.Item>
            </Col>
            <Col span={8}>
              <Form.Item
                name="start_date"
                label="Ngày bắt đầu"
              >
                <DatePicker 
                  style={{ width: '100%' }}
                  placeholder="Chọn ngày bắt đầu"
                  format="DD/MM/YYYY"
                />
              </Form.Item>
            </Col>
            <Col span={8}>
              <Form.Item
                name="end_date"
                label="Ngày kết thúc"
              >
                <DatePicker 
                  style={{ width: '100%' }}
                  placeholder="Chọn ngày kết thúc"
                  format="DD/MM/YYYY"
                />
              </Form.Item>
            </Col>
          </Row>

          <Row gutter={16}>
            <Col span={8}>
              <Form.Item
                label="Thời gian ước tính (giờ)"
              >
                <InputNumber
                  min={0}
                  max={1000}
                  value={estimatedHours}
                  onChange={(value) => setEstimatedHours(value || 0)}
                  style={{ width: '100%' }}
                  placeholder="0"
                />
              </Form.Item>
            </Col>
            <Col span={8}>
              <Form.Item
                label="Thời gian thực tế (giờ)"
              >
                <InputNumber
                  min={0}
                  max={1000}
                  value={actualHours}
                  onChange={(value) => setActualHours(value || 0)}
                  style={{ width: '100%' }}
                  placeholder="0"
                  disabled={mode === 'create'}
                />
              </Form.Item>
            </Col>
            <Col span={8}>
              <Form.Item
                name="progress"
                label="Tiến độ (%)"
              >
                <InputNumber
                  min={0}
                  max={100}
                  style={{ width: '100%' }}
                  placeholder="0"
                  formatter={value => `${value}%`}
                  parser={value => value!.replace('%', '')}
                />
              </Form.Item>
            </Col>
          </Row>
        </Card>

        {/* Dependencies & Advanced */}
        <Card size="small" title="Phụ thuộc & Nâng cao" className="mb-4">
          <Row gutter={16}>
            <Col span={12}>
              <Form.Item
                label={
                  <Space>
                    <LinkOutlined />
                    Nhiệm vụ phụ thuộc
                    <Tooltip title="Các nhiệm vụ cần hoàn thành trước khi bắt đầu nhiệm vụ này">
                      <InfoCircleOutlined />
                    </Tooltip>
                  </Space>
                }
              >
                <Select
                  mode="multiple"
                  placeholder="Chọn nhiệm vụ phụ thuộc"
                  value={dependencies}
                  onChange={setDependencies}
                  disabled={!selectedProject}
                >
                  {filteredTasks.map(task => (
                    <Option key={task.id} value={task.id}>
                      {task.name}
                    </Option>
                  ))}
                </Select>
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item
                name="conditional_tag"
                label={
                  <Space>
                    Thẻ điều kiện
                    <Tooltip title="Thẻ để xác định điều kiện hiển thị nhiệm vụ">
                      <InfoCircleOutlined />
                    </Tooltip>
                  </Space>
                }
              >
                <Input placeholder="Ví dụ: Material/Flooring/Granite" />
              </Form.Item>
            </Col>
          </Row>

          <Row gutter={16}>
            <Col span={12}>
              <Form.Item
                name="tags"
                label="Thẻ"
              >
                <Select
                  mode="tags"
                  placeholder="Thêm thẻ"
                  tokenSeparators={[',']}
                >
                </Select>
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item
                name="is_milestone"
                label="Cột mốc quan trọng"
                valuePropName="checked"
              >
                <Switch checkedChildren="Có" unCheckedChildren="Không" />
              </Form.Item>
            </Col>
          </Row>
        </Card>

        {/* Attachments */}
        <Card size="small" title="Tệp đính kèm" className="mb-4">
          <Upload
            multiple
            listType="text"
            fileList={attachments}
            onChange={({ fileList }) => setAttachments(fileList)}
            beforeUpload={() => false} // Prevent auto upload
          >
            <Button icon={<UploadOutlined />}>Chọn tệp</Button>
          </Upload>
        </Card>

        {/* Progress Indicator */}
        {mode === 'edit' && estimatedHours > 0 && (
          <Alert
            message="Tiến độ thời gian"
            description={
              <div>
                <p>Thời gian ước tính: {estimatedHours} giờ</p>
                <p>Thời gian thực tế: {actualHours} giờ</p>
                <Progress 
                  percent={Math.round((actualHours / estimatedHours) * 100)}
                  status={actualHours > estimatedHours ? 'exception' : 'active'}
                  format={percent => `${percent}%`}
                />
              </div>
            }
            type={actualHours > estimatedHours ? 'warning' : 'info'}
            className="mb-4"
          />
        )}

        {/* Form Actions */}
        <Divider />
        <Row justify="end">
          <Space>
            <Button onClick={onCancel}>
              Hủy
            </Button>
            <Button type="primary" htmlType="submit" loading={loading}>
              {mode === 'create' ? 'Tạo nhiệm vụ' : 'Cập nhật nhiệm vụ'}
            </Button>
          </Space>
        </Row>
      </Form>
    </Card>
  );
};

export default TaskForm;