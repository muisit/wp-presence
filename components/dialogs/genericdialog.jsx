import React from 'react';
import { api_misc, ap_misc } from "../api.js";
import { Button } from 'primereact/button';
import { Dialog } from 'primereact/dialog';
import { Dropdown } from 'primereact/dropdown';
import { InputText } from 'primereact/inputtext';
import { InputNumber } from 'primereact/inputnumber';
import { Checkbox } from 'primereact/checkbox';
import { Calendar } from 'primereact/calendar';
import { find_value_of_attribute, attribute_by_name, create_attribute_from_template } from './../functions';

export default class GenericDialog extends React.Component {
    constructor(props, context) {
        super(props, context);
        this.state = {
            old_status:-1
        }
        this.abortType=(this.props.value && this.props.value.type) || 'none';

        this.enums={};
        for(var i in this.props.template.attributes) {
            var attr=this.props.template.attributes[i];
            if(attr.type=='enum') {
                this.enums[attr.name] = attr.value.split(' ');
            }
        }
    }

    loading = (state) => {
        if(this.props.onLoad) this.props.onLoad(state);
    }

    close = () => {
        if(this.props.onClose) this.props.onClose();
    }

    save = (item) => {
        if(this.props.onSave) this.props.onSave(item);
        this.close();
    }

    delete = (item) => {
        if(this.props.onDelete) this.props.onDelete(item);
        this.close();
    }

    onCloseDialog = (event) => {
        this.loading(true);

        api_misc(this.abortType,'item','save',this.props.value)
            .then((json) => {
                this.loading(false);
                this.save(this.props.value);
            })
            .catch((err) => {
                console.log("caught error ",err);
                if(err.response.data.messages && err.response.data.messages.length) {
                    var txt="";
                    for(var i=0;i<err.response.data.messages.length;i++) {
                       txt+=err.response.data.messages[i]+"\r\n";
                    }
                    alert(txt);
                }
                else {
                    alert('Error storing the data. Please try again');
                }
                this.loading(false);
            });
    }

    onCancelDialog = (event) => {
        this.close();
    }    

    onChangeEl = (event) => {
        if(!event.target || !event.target.value) return;
        var item=Object.assign({},this.props.value);
        switch(event.target.name) {
        case 'name': item[event.target.name] = event.target.value; break;
        }
        if (this.props.onChange) this.props.onChange(item);
    }

    onChangeAttr = (attr,e) => {
        var value = e.value || e.target.value;
        var abyname=attribute_by_name(this.props.value.attributes);

        var newattrs = this.props.template.attributes.map((item) => {
            console.log("finding attribute ",item.name);
            var newitem = abyname[item.name];
            if (!newitem) {
                newitem = create_attribute_from_template(item);
            }
            else {
                // make sure we migrate any changes in the back-end
                newitem = Object.assign({},item,newitem);
            }
            if(item.name == attr.name) {
                console.log("replacing item with new value");
                newitem=Object.assign({}, item, newitem);
                if(attr.type=='date') {
                    var dt=new Date(value);
                    value = dt.getFullYear() + "-" + ((dt.getMonth() < 9) ? '0' : '') + (dt.getMonth()+1) + "-" + ((dt.getDate() < 10) ? '0':'') + dt.getDate();
                }
                newitem.value = value;
                newitem.type = attr.type; // copy the type field. This is skipped in the front-end
            }
            else {
                console.log("keeping original item from ",newitem);
                
            }
            return newitem;
        });
        if(newattrs !== null) {
            var item=this.props.value;
            console.log("attributes are now ",newattrs);
            item.attributes=newattrs;
            if (this.props.onChange) this.props.onChange(item);
        }
    }

    onDeleteDialog = (event) => {
        if(confirm('Are you sure you want to delete '+ this.props.value.type + " " + this.props.value.name + "? This action cannot be undone!")) {
            this.loading(true);
            api_misc(this.abortType,'item','delete',{ id: this.props.value.id})
            .then((json) => {
                this.loading(false);
                this.delete();
            })
            .catch((err) => {
                if(err.response.data.messages && err.response.data.messages.length) {
                    var txt="";
                    for(var i=0;i<err.response.data.messages.length;i++) {
                        txt+=err.response.data.messages[i]+"\r\n";
                    }
                    alert(txt);
                }
                else {
                    alert('Error removing the data. Please try again');
                }
            });    
        }
    }

    onSoftDelete = (state) => {
        api_misc(this.abortType, 'item', 'softdelete', { id: this.props.value.id, softdelete:state })
            .then((json) => {
                this.loading(false);
                if(state) this.props.value.deleted='yes';
                else if(this.props.value.deleted) {
                    delete this.props.value.deleted;
                }
                this.save(this.props.value);
            })
            .catch((err) => {
                if (err.response.data.messages && err.response.data.messages.length) {
                    var txt = "";
                    for (var i = 0; i < err.response.data.messages.length; i++) {
                        txt += err.response.data.messages[i] + "\r\n";
                    }
                    alert(txt);
                }
                else {
                    alert('Error removing the data. Please try again');
                }
            });
    }

    render() {
        if(!this.props.value) {
            return (<div></div>);
        }
        var footer=(<div>
        <Button label="Cancel" icon="pi pi-times" className="p-button-warning p-button-raised p-button-text" onClick={this.onCancelDialog} />
        <Button label="Save" icon="pi pi-check" className="p-button-raised" onClick={this.onCloseDialog} />
</div>);
        if(this.props.value.id >0 && ! this.props.onlyAdd) {
            var softdelete = (<Button label="SoftDel" icon="pi pi-trash" className="p-button-danger p-button-raised p-button-text" onClick={()=>this.onSoftDelete(true)} />);
            if(this.props.value.deleted) {
                softdelete = (<Button label="UnDel" icon="pi pi-trash" className="p-button-danger p-button-raised p-button-text" onClick={() => this.onSoftDelete(false)} />);
            }
            footer=(<div>
                {softdelete}
                <Button label="Remove" icon="pi pi-trash" className="p-button-danger p-button-raised p-button-text" onClick={this.onDeleteDialog} />
                <Button label="Cancel" icon="pi pi-times" className="p-button-warning p-button-raised p-button-text" onClick={this.onCancelDialog} />
                <Button label="Save" icon="pi pi-check" className="p-button-raised" onClick={this.onCloseDialog} />
</div>);
        }

        return (<Dialog header={"Edit " + this.props.value.type} position="center" visible={this.props.display} style={{ width: this.props.width || '50vw' }} modal={true} footer={footer} onHide={this.onCancelDialog} className={"generic " + this.props.value.type}>
      <div className='attribute'>
        <label>Name</label>
        <div className='inputs'>
          <div className='input'>
              <InputText name='name' value={this.props.value.name} onChange={this.onChangeEl} placeholder='Name'/>
          </div>
        </div>
      </div>
      {this.props.template.attributes && this.props.template.attributes.map((attr,idx) => {
          var value=find_value_of_attribute(attr.name, this.props.value.attributes);
          return (
          <div className='attribute' key={idx}>
          <label>{attr.name}</label>
          <div className='inputs'>
            <div className='input'>
              {attr && attr.type === 'string' && (
                <InputText value={value} onChange={(e) => this.onChangeAttr(attr,e)}/>
              )}
              {attr && attr.type === 'number' && (
                <InputNumber className='inputint' onChange={(e) => this.onChangeAttr(attr,e)}
                mode="decimal" inputMode='decimal' minFractionDigits={1} maxFractionDigits={5} min={0} useGrouping={false}
                value={parseFloat(value)}></InputNumber>
              )}
              {attr && attr.type === 'int' && (
                <InputNumber className='inputint' onChange={(e) => this.onChangeAttr(attr,e)}
                mode="decimal" useGrouping={false} value={parseInt(value)}></InputNumber>
              )}
              {attr && attr.type === 'year' && (
                <InputNumber className='inputint' onChange={(e) => this.onChangeAttr(attr,e)}
                min={1900} max={2100} mode="decimal" useGrouping={false} value={parseInt(value)}></InputNumber>
              )}
              {attr && attr.type === 'date' && (
                <Calendar appendTo={document.body} onChange={(e) => this.onChangeAttr(attr,e)}
                dateFormat="yy-mm-dd" value={new Date(value)}></Calendar>
              )}
              {attr && attr.type === 'enum' && (
                <Dropdown appendTo={document.body} onChange={(e) => this.onChangeAttr(attr,e)} options={this.enums[attr.name]} value={value}></Dropdown>
              )}
              {attr && attr.type === 'check' && (
                <Checkbox checked={value=='yes'} onChange={(e) => this.onChangeAttr(attr, {value: e.checked?"yes":"no"})} />
              )}
            </div>
          </div>
        </div>
      )})}
</Dialog>
);
    }
}

