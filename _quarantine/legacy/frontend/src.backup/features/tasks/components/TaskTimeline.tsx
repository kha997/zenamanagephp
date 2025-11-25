import React, { useState } from 'react';
import {
  Timeline,
  Card,
  Tag,
  Avatar,
  Space,
  Button,
  Tooltip,
  Dropdown,
  Menu,
  Input,
  DatePicker,
  Select,
  Modal,
  Form,
  message
} from 'antd';
import {
  ClockCircleOutlined,
  CheckCircleOutlined,
  ExclamationCircleOutlined,
  UserOutlined,
  EditOutlined,
  DeleteOutlined,
  PlusOutlined,
  CalendarOutlined,
  FileTextOutlined,
  LinkOutlined
} from '@ant-design/icons';
import { Task, TimelineEvent, User } from '../../types';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';

dayjs.extend(relativeTime);

const { TextArea } = Input;
const { Option } = Select;

interface TaskTimelineProps {
  task: Task;
  events: TimelineEvent[];
  users: User[];
  onAddEvent: (event: Partial<TimelineEvent>) => void;
  onUpdateEvent: (eventId: string, event: Partial<TimelineEvent>) => void;
  onDeleteEvent: (eventId: string) => void;
  currentUser: User;
  loading?: boolean;
}

const TaskTimeline: React.FC<TaskTimelineProps> = ({
  task,
  events,
  users,
  onAddEvent,
  onUpdateEvent,
  onDeleteEvent,
  currentUser,
  loading = false
}) => {
  const [isAddModalVisible, setIsAddModalVisible] = useState(false);
  const [editingEvent, setEditingEvent] = useState<TimelineEvent | null>(null);
  const [form] = Form.useForm();

  const eventTypes = [
    { value: 'created', label: 'Tạo nhiệm vụ', color: 'blue', icon: <PlusOutlined /> },
    { value: 'updated', label: 'Cập nhật', color: 'orange', icon: <EditOutlined /> },
    { value: 'status_changed', label: 'Thay đổi trạng thái', color: 'purple', icon: <ExclamationCircleOutlined /> },
    { value: 'assigned', label: 'Phân công', color: 'green', icon: <UserOutlined /> },
    { value: 'comment', label: 'Bình luận', color: 'cyan', icon: <FileTextOutlined /> },
    { value: 'completed', label: 'Hoàn thành', color: 'success', icon: <CheckCircleOutlined /> },
    { value: 'milestone', label: 'Cột mốc', color: 'gold', icon: <CalendarOutlined /> },
    { value: 'dependency', label: 'Phụ thuộc', color: 'magenta', icon: <LinkOutlined /> }
  ];

  const getEventTypeConfig = (type: string) => {
    return eventTypes.find(et => et.value === type) || eventTypes[0];
  };

  const handleAddEvent = () => {
    setEditingEvent(null);
    form.resetFields();
    setIsAddModalVisible(true);
  };

  const handleEditEvent = (event: TimelineEvent) => {
    setEditingEvent(event);
    form.setFieldsValue({
      type: event.type,
      title: event.title,
      description: event.description,
      created_at: dayjs(event.created_at)
    });
    setIsAddModalVisible(true);
  };

  const handleSubmitEvent = (values: any) => {
    const eventData = {
      ...values,
      task_id: task.id,
      created_by: currentUser.id,
      created_at: values.created_at?.toISOString() || new Date().toISOString()
    };

    if (editingEvent) {
      onUpdateEvent(editingEvent.id, eventData);
    } else {
      onAddEvent(eventData);
    }

    setIsAddModalVisible(false);
    form.resetFields();
    message.success(editingEvent ? 'Cập nhật sự kiện thành công' : 'Thêm sự kiện thành công');
  };

  const handleDeleteEvent = (eventId: string) => {
    Modal.confirm({
      title: 'Xác nhận xóa',
      content: 'Bạn có chắc chắn muốn xóa sự kiện này?',
      okText: 'Xóa',
      cancelText: 'Hủy',
      okType: 'danger',
      onOk: () => {
        onDeleteEvent(eventId);
        message.success('Xóa sự kiện thành công');
      }
    });
  };

  const getEventMenu = (event: TimelineEvent) => (
    <Menu>
      <Menu.Item key="edit" onClick={() => handleEditEvent(event)}>
        <EditOutlined /> Chỉnh sửa
      </Menu.Item>
      <Menu.Item key="delete" onClick={() => handleDeleteEvent(event.id)}>
        <DeleteOutlined /> Xóa
      </Menu.Item>
    </Menu>
  );

  const sortedEvents = [...events].sort((a, b) => 
    new Date(b.created_at).getTime() - new Date(a.created_at).getTime()
  );

  return (
    <Card 
      title={
        <Space>
          <ClockCircleOutlined />
          Timeline nhiệm vụ
        </Space>
      }
      extra={
        <Button 
          type="primary" 
          icon={<PlusOutlined />} 
          onClick={handleAddEvent}
          size="small"
        >
          Thêm sự kiện
        </Button>
      }
      className="task-timeline-card"
    >
      <Timeline mode="left">
        {sortedEvents.map((event, index) => {
          const typeConfig = getEventTypeConfig(event.type);
          const user = users.find(u => u.id === event.created_by);
          
          return (
            <Timeline.Item
              key={event.id}
              dot={
                <Avatar 
                  size="small" 
                  style={{ backgroundColor: typeConfig.color }}
                  icon={typeConfig.icon}
                />
              }
              color={typeConfig.color}
            >
              <div className="timeline-event">
                <div className="event-header">
                  <Space>
                    <Tag color={typeConfig.color}>{typeConfig.label}</Tag>
                    <span className="event-time">
                      {dayjs(event.created_at).format('DD/MM/YYYY HH:mm')}
                    </span>
                    <span className="event-relative-time">
                      ({dayjs(event.created_at).fromNow()})
                    </span>
                    {event.created_by === currentUser.id && (
                      <Dropdown overlay={getEventMenu(event)} trigger={['click']}>
                        <Button type="text" size="small" icon={<EditOutlined />} />
                      </Dropdown>
                    )}
                  </Space>
                </div>
                
                <div className="event-content">
                  <h4 className="event-title">{event.title}</h4>
                  {event.description && (
                    <p className="event-description">{event.description}</p>
                  )}
                </div>
                
                <div className="event-footer">
                  <Space>
                    <Avatar size="small" src={user?.avatar} icon={<UserOutlined />} />
                    <span className="event-author">{user?.name || 'Unknown User'}</span>
                  </Space>
                </div>
              </div>
            </Timeline.Item>
          );
        })}
        
        {sortedEvents.length === 0 && (
          <Timeline.Item dot={<ClockCircleOutlined />}>
            <div className="no-events">
              <p>Chưa có sự kiện nào được ghi lại</p>
              <Button type="link" onClick={handleAddEvent}>
                Thêm sự kiện đầu tiên
              </Button>
            </div>
          </Timeline.Item>
        )}
      </Timeline>

      {/* Add/Edit Event Modal */}
      <Modal
        title={editingEvent ? 'Chỉnh sửa sự kiện' : 'Thêm sự kiện mới'}
        open={isAddModalVisible}
        onCancel={() => setIsAddModalVisible(false)}
        footer={null}
        width={600}
      >
        <Form
          form={form}
          layout="vertical"
          onFinish={handleSubmitEvent}
          initialValues={{
            type: 'comment',
            created_at: dayjs()
          }}
        >
          <Form.Item
            name="type"
            label="Loại sự kiện"
            rules={[{ required: true, message: 'Vui lòng chọn loại sự kiện' }]}
          >
            <Select placeholder="Chọn loại sự kiện">
              {eventTypes.map(type => (
                <Option key={type.value} value={type.value}>
                  <Space>
                    {type.icon}
                    <Tag color={type.color}>{type.label}</Tag>
                  </Space>
                </Option>
              ))}
            </Select>
          </Form.Item>

          <Form.Item
            name="title"
            label="Tiêu đề"
            rules={[{ required: true, message: 'Vui lòng nhập tiêu đề' }]}
          >
            <Input placeholder="Nhập tiêu đề sự kiện" />
          </Form.Item>

          <Form.Item
            name="description"
            label="Mô tả"
          >
            <TextArea 
              rows={4} 
              placeholder="Mô tả chi tiết về sự kiện"
              showCount
              maxLength={500}
            />
          </Form.Item>

          <Form.Item
            name="created_at"
            label="Thời gian"
          >
            <DatePicker 
              showTime
              style={{ width: '100%' }}
              format="DD/MM/YYYY HH:mm"
            />
          </Form.Item>

          <Form.Item>
            <Space>
              <Button onClick={() => setIsAddModalVisible(false)}>
                Hủy
              </Button>
              <Button type="primary" htmlType="submit" loading={loading}>
                {editingEvent ? 'Cập nhật' : 'Thêm sự kiện'}
              </Button>
            </Space>
          </Form.Item>
        </Form>
      </Modal>
    </Card>
  );
};

export default TaskTimeline;