import { describe, it, expect } from 'vitest';
import {
  getOverallStatusLabel,
  getScheduleStatusLabel,
  getCostStatusLabel,
  getOverallStatusTone,
} from '../healthStatus';

describe('healthStatus helpers', () => {
  describe('getOverallStatusLabel', () => {
    it('should return correct Vietnamese labels for known statuses', () => {
      expect(getOverallStatusLabel('good')).toBe('Tốt');
      expect(getOverallStatusLabel('warning')).toBe('Cảnh báo');
      expect(getOverallStatusLabel('critical')).toBe('Nguy cấp');
    });

    it('should return the raw status string for unknown statuses', () => {
      expect(getOverallStatusLabel('weird_status')).toBe('weird_status');
      expect(getOverallStatusLabel('unknown')).toBe('unknown');
    });
  });

  describe('getScheduleStatusLabel', () => {
    it('should return correct Vietnamese labels for known statuses', () => {
      expect(getScheduleStatusLabel('on_track')).toBe('Đúng tiến độ');
      expect(getScheduleStatusLabel('at_risk')).toBe('Có rủi ro chậm');
      expect(getScheduleStatusLabel('delayed')).toBe('Đang chậm tiến độ');
      expect(getScheduleStatusLabel('no_tasks')).toBe('Chưa có task nào');
    });

    it('should return the raw status string for unknown statuses', () => {
      expect(getScheduleStatusLabel('weird_status')).toBe('weird_status');
      expect(getScheduleStatusLabel('unknown')).toBe('unknown');
    });
  });

  describe('getCostStatusLabel', () => {
    it('should return correct Vietnamese labels for known statuses', () => {
      expect(getCostStatusLabel('on_budget')).toBe('Trong ngân sách');
      expect(getCostStatusLabel('at_risk')).toBe('Chi phí có rủi ro vượt');
      expect(getCostStatusLabel('over_budget')).toBe('Vượt ngân sách');
      expect(getCostStatusLabel('no_data')).toBe('Chưa có dữ liệu chi phí');
    });

    it('should return the raw status string for unknown statuses', () => {
      expect(getCostStatusLabel('weird_status')).toBe('weird_status');
      expect(getCostStatusLabel('unknown')).toBe('unknown');
    });
  });

  describe('getOverallStatusTone', () => {
    it('should return correct tones for known statuses', () => {
      expect(getOverallStatusTone('good')).toBe('success');
      expect(getOverallStatusTone('warning')).toBe('warning');
      expect(getOverallStatusTone('critical')).toBe('danger');
    });

    it('should return neutral for unknown statuses', () => {
      expect(getOverallStatusTone('weird_status')).toBe('neutral');
      expect(getOverallStatusTone('unknown')).toBe('neutral');
    });
  });
});

