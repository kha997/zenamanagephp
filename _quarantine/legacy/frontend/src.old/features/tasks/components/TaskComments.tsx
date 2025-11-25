import React, { useState } from 'react';
import {
  Card,
  Comment,
  Avatar,
  Form,
  Button,
  Input,
  List,
  Space,
  Dropdown,
  Menu,
  Modal,
  message,
  Tag,
  Tooltip,
  Upload,
  Image
} from 'antd';
import {
  MessageOutlined,
  EditOutlined,
  DeleteOutlined,
  LikeOutlined,
  LikeFilled,
  ReplyArrowIcon,
  PaperClipOutlined,
  SendOutlined,
  UserOutlined
} from '@ant-design/icons';
import { TaskComment, User } from '../../types';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';

dayjs.extend(relativeTime);

const { TextArea } = Input;

interface TaskCommentsProps {
  taskId: string;
  comments: TaskComment[];
  users: User[];
  currentUser: User;
  onAddComment: (comment: Partial<TaskComment>) => void;
  onUpdateComment: (commentId: string, comment: Partial<TaskComment>) => void;
  onDeleteComment: (commentId: string) => void;
  onLikeComment: (commentId: string) => void;
  loading?: boolean;
}

const TaskComments: React.FC<TaskCommentsProps> = ({
  taskId,
  comments,
  users,
  currentUser,
  onAddComment,
  onUpdateComment,
  onDeleteComment,
  onLikeComment,
  loading = false
}) => {
  const [form] = Form.useForm();
  const [editingComment, setEditingComment] = useState<TaskComment | null>(null);
  const [replyingTo, setReplyingTo] = useState<string | null>(null);
  const [attachments, setAttachments] = useState<any[]>([]);

  const handleSubmitComment = (values: any) => {
    const commentData = {
      task_id: taskId,
      content: values.content,
      parent_id: replyingTo,
      attachments: attachments.map(file => file.response?.url || file.url).filter(Boolean),
      created_by: currentUser.id
    };

    if (editingComment) {
      onUpdateComment(editingComment.id, commentData);
      setEditingComment(null);
      message.success('Cập nhật bình luận thành công');
    } else {
      onAddComment(commentData);
      message.success('Thêm bình luận thành công');
    }

    form.resetFields();
    setAttachments([]);
    setReplyingTo(null);
  };

  const handleEditComment = (comment: TaskComment) => {
    setEditingComment(comment);
    form.setFieldsValue({ content: comment.content });
    setAttachments(comment.attachments?.map((url, index) => ({
      uid: index,
      name: `attachment-${index}`,
      status: 'done',
      url
    })) || []);
  };

  const handleDeleteComment = (commentId: string) => {
    Modal.confirm({
      title: 'Xác nhận xóa',
      content: 'Bạn có chắc chắn muốn xóa bình luận này?',
      okText: 'Xóa',
      cancelText: 'Hủy',
      okType: 'danger',
      onOk: () => {
        onDeleteComment(commentId);
        message.success('Xóa bình luận thành công');
      }
    });
  };

  const handleReply = (commentId: string) => {
    setReplyingTo(commentId);
    setEditingComment(null);
    form.resetFields();
  };

  const handleCancelEdit = () => {
    setEditingComment(null);
    setReplyingTo(null);
    form.resetFields();
    setAttachments([]);
  };

  const getCommentMenu = (comment: TaskComment) => (
    <Menu>
      <Menu.Item key="reply" onClick={() => handleReply(comment.id)}>
        <ReplyArrowIcon /> Trả lời
      </Menu.Item>
      {comment.created_by === currentUser.id && (
        <>
          <Menu.Item key="edit" onClick={() => handleEditComment(comment)}>
            <EditOutlined /> Chỉnh sửa
          </Menu.Item>
          <Menu.Item key="delete" onClick={() => handleDeleteComment(comment.id)}>
            <DeleteOutlined /> Xóa
          </Menu.Item>
        </>
      )}
    </Menu>
  );

  const renderComment = (comment: TaskComment) => {
    const user = users.find(u => u.id === comment.created_by);
    const isLiked = comment.likes?.includes(currentUser.id);
    const likesCount = comment.likes?.length || 0;
    const replies = comments.filter(c => c.parent_id === comment.id);

    const actions = [
      <Tooltip title={isLiked ? 'Bỏ thích' : 'Thích'}>
        <Button
          type="text"
          size="small"
          icon={isLiked ? <LikeFilled style={{ color: '#1890ff' }} /> : <LikeOutlined />}
          onClick={() => onLikeComment(comment.id)}
        >
          {likesCount > 0 && likesCount}
        </Button>
      </Tooltip>,
      <Dropdown overlay={getCommentMenu(comment)} trigger={['click']}>
        <Button type="text" size="small">
          Thêm
        </Button>
      </Dropdown>
    ];

    return (
      <Comment
        key={comment.id}
        author={user?.name || 'Unknown User'}
        avatar={
          <Avatar 
            src={user?.avatar} 
            icon={<UserOutlined />}
            size="small"
          />
        }
        content={
          <div>
            <p>{comment.content}</p>
            {comment.attachments && comment.attachments.length > 0 && (
              <div className="comment-attachments">
                <Space wrap>
                  {comment.attachments.map((url, index) => (
                    <div key={index} className="attachment-item">
                      {url.match(/\.(jpg|jpeg|png|gif)$/i) ? (
                        <Image
                          width={100}
                          src={url}
                          alt={`Attachment ${index + 1}`}
                        />
                      ) : (
                        <Button 
                          type="link" 
                          icon={<PaperClipOutlined />}
                          href={url}
                          target="_blank"
                        >
                          Tệp đính kèm {index + 1}
                        </Button>
                      )}
                    </div>
                  ))}
                </Space>
              </div>
            )}
          </div>
        }
        datetime={
          <Tooltip title={dayjs(comment.created_at).format('DD/MM/YYYY HH:mm:ss')}>
            <span>{dayjs(comment.created_at).fromNow()}</span>
          </Tooltip>
        }
        actions={actions}
      >
        {replies.length > 0 && (
          <div className="comment-replies">
            {replies.map(reply => renderComment(reply))}
          </div>
        )}
      </Comment>
    );
  };

  const topLevelComments = comments.filter(c => !c.parent_id);

  return (
    <Card 
      title={
        <Space>
          <MessageOutlined />
          Bình luận ({comments.length})
        </Space>
      }
      className="task-comments-card"
    >
      {/* Comment Form */}
      <Form
        form={form}
        onFinish={handleSubmitComment}
        className="comment-form"
      >
        {replyingTo && (
          <div className="reply-indicator">
            <Tag closable onClose={() => setReplyingTo(null)}>
              Đang trả lời bình luận
            </Tag>
          </div>
        )}
        
        {editingComment && (
          <div className="edit-indicator">
            <Tag color="orange" closable onClose={handleCancelEdit}>
              Đang chỉnh sửa bình luận
            </Tag>
          </div>
        )}

        <Form.Item
          name="content"
          rules={[{ required: true, message: 'Vui lòng nhập nội dung bình luận' }]}
        >
          <TextArea
            rows={3}
            placeholder={replyingTo ? 'Nhập phản hồi...' : 'Nhập bình luận...'}
            showCount
            maxLength={1000}
          />
        </Form.Item>

        <div className="comment-form-actions">
          <Space>
            <Upload
              multiple
              listType="text"
              fileList={attachments}
              onChange={({ fileList }) => setAttachments(fileList)}
              beforeUpload={() => false}
              showUploadList={{ showRemoveIcon: true }}
            >
              <Button icon={<PaperClipOutlined />} size="small">
                Đính kèm
              </Button>
            </Upload>
            
            {(editingComment || replyingTo) && (
              <Button onClick={handleCancelEdit} size="small">
                Hủy
              </Button>
            )}
            
            <Button 
              type="primary" 
              htmlType="submit" 
              icon={<SendOutlined />}
              loading={loading}
              size="small"
            >
              {editingComment ? 'Cập nhật' : replyingTo ? 'Trả lời' : 'Gửi'}
            </Button>
          </Space>
        </div>
      </Form>

      {/* Comments List */}
      <div className="comments-list">
        {topLevelComments.length > 0 ? (
          <List
            dataSource={topLevelComments}
            renderItem={renderComment}
            className="comments-list-container"
          />
        ) : (
          <div className="no-comments">
            <p>Chưa có bình luận nào</p>
            <p>Hãy là người đầu tiên bình luận về nhiệm vụ này!</p>
          </div>
        )}
      </div>
    </Card>
  );
};

export default TaskComments;